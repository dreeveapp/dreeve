<?php

declare(strict_types=1);

namespace App\Console\Cache;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Render\RenderCache;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: ClearStaleRenderCacheConsoleCommand::NAME, description: 'Empty the render cache when it was written by another app version')]
final class ClearStaleRenderCacheConsoleCommand extends Command
{
    public const string NAME = 'app:cache:render:clear-stale';
    private const string VERSION_MARKER_PATH = 'render-cache.version';

    public function __construct(
        private readonly RenderCache $renderCache,
        private readonly FilesystemOperator $buildStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $currentVersion = AppVersion::getSemanticVersion();

        if ($this->versionThatWroteTheCache() === $currentVersion) {
            $output->writeln('Render cache matches the current app version');

            return Command::SUCCESS;
        }

        $this->renderCache->clear();
        $this->buildStorage->write(self::VERSION_MARKER_PATH, $currentVersion);
        $output->writeln('Cleared the render cache, it was written by another app version');

        return Command::SUCCESS;
    }

    private function versionThatWroteTheCache(): ?string
    {
        if (!$this->buildStorage->fileExists(self::VERSION_MARKER_PATH)) {
            return null;
        }

        return trim($this->buildStorage->read(self::VERSION_MARKER_PATH));
    }
}

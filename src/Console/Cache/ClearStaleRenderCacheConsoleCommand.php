<?php

declare(strict_types=1);

namespace App\Console\Cache;

use App\Infrastructure\Cache\RenderCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cache:render:clear-stale', description: 'Clear rendered pages left behind by a previous app version')]
final class ClearStaleRenderCacheConsoleCommand extends Command
{
    public function __construct(
        private readonly RenderCache $renderCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        if (!$this->renderCache->clearWhenAppVersionChanged()) {
            $output->writeln('Render cache is up to date with the current app version');

            return Command::SUCCESS;
        }

        $output->writeln('Cleared the render cache of the previous app version');

        return Command::SUCCESS;
    }
}

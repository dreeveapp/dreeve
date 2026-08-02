<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Infrastructure\Cache\RenderCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: PruneRenderCacheConsoleCommand::NAME, description: 'Delete expired rendered pages from the render cache')]
final class PruneRenderCacheConsoleCommand extends Command
{
    public const string NAME = 'app:cache:render:prune';

    public function __construct(
        private readonly RenderCache $renderCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $this->renderCache->prune();
        $output->writeln('Pruned expired entries from the render cache');

        return Command::SUCCESS;
    }
}

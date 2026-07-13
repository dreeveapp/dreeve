<?php

declare(strict_types=1);

namespace App\Console\Database;

use App\Infrastructure\Doctrine\Migrations\CouldNotRunMigrations;
use App\Infrastructure\Doctrine\Migrations\MigrationRunner;
use App\Infrastructure\Doctrine\Migrations\MigrationsOutdated;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:db:migrate', description: 'Run database migrations')]
final class MigrateDatabaseConsoleCommand extends Command
{
    public function __construct(
        private readonly MigrationRunner $migrationRunner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        try {
            $this->migrationRunner->run($output);
        } catch (MigrationsOutdated|CouldNotRunMigrations $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

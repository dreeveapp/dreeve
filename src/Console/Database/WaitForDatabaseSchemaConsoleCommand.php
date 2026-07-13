<?php

declare(strict_types=1);

namespace App\Console\Database;

use App\Infrastructure\Doctrine\Migrations\MigrationRunner;
use App\Infrastructure\Time\Sleep;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:db:wait-for-schema', description: 'Wait until the database schema is up to date')]
final class WaitForDatabaseSchemaConsoleCommand extends Command
{
    private const int POLL_INTERVAL_IN_SECONDS = 2;
    private const int TIMEOUT_IN_SECONDS = 300;

    public function __construct(
        private readonly MigrationRunner $migrationRunner,
        private readonly Sleep $sleep,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $maxAttempts = intdiv(self::TIMEOUT_IN_SECONDS, self::POLL_INTERVAL_IN_SECONDS);
        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            if ($this->databaseSchemaIsUpToDate()) {
                return Command::SUCCESS;
            }

            $this->sleep->sweetDreams(self::POLL_INTERVAL_IN_SECONDS);
        }

        $output->error(sprintf(
            'The database schema was still not up to date after %d seconds. The app container runs the migrations, make sure it is up and running.',
            self::TIMEOUT_IN_SECONDS
        ));

        return Command::FAILURE;
    }

    private function databaseSchemaIsUpToDate(): bool
    {
        try {
            return $this->migrationRunner->isAtLatestVersion();
        } catch (DbalException) {
            // The database might not exist yet, or it might be locked by the process running the migrations.
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Console\ConsoleApplication;
use App\Infrastructure\Time\Sleep;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class DoctrineMigrationRunner implements MigrationRunner
{
    private const int MAX_ATTEMPTS = 3;
    private const int SECONDS_BETWEEN_ATTEMPTS = 5;

    public function __construct(
        private MigrationSquashHandler $migrationSquashHandler,
        private Sleep $sleep,
    ) {
    }

    public function run(OutputInterface $output): void
    {
        $this->migrationSquashHandler->handle();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $exitCode = $this->migrate($output);

                if (0 !== $exitCode) {
                    throw new CouldNotRunMigrations();
                }

                return;
            } catch (LockWaitTimeoutException $lockWaitTimeout) {
                // Another process was writing to the database.
                if (self::MAX_ATTEMPTS === $attempt) {
                    throw new CouldNotRunMigrations('The database was locked by another process, migrations could not run.', previous: $lockWaitTimeout);
                }

                $output->writeln(sprintf(
                    '<comment>The database is locked by another process, retrying in %d seconds...</comment>',
                    self::SECONDS_BETWEEN_ATTEMPTS
                ));
                $this->sleep->sweetDreams(self::SECONDS_BETWEEN_ATTEMPTS);
            }
        }
    }

    private function migrate(OutputInterface $output): int
    {
        $input = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
        ]);
        $input->setInteractive(false);

        return ConsoleApplication::get()->doRun(
            input: $input,
            output: $output
        );
    }

    public function isAtLatestVersion(): bool
    {
        $output = new MigrationConsoleOutput();
        ConsoleApplication::get()->doRun(
            new ArrayInput([
                'command' => 'doctrine:migrations:status',
            ]),
            $output
        );

        return str_contains($output->getDisplay(), 'Already at latest version');
    }
}

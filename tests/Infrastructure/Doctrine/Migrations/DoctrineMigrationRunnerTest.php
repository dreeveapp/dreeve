<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Console\ConsoleApplication;
use App\Infrastructure\Doctrine\Migrations\CouldNotRunMigrations;
use App\Infrastructure\Doctrine\Migrations\DoctrineMigrationRunner;
use App\Infrastructure\Doctrine\Migrations\MigrationSquashHandler;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

class DoctrineMigrationRunnerTest extends ContainerTestCase
{
    private PausedClock $clock;
    private BufferedOutput $output;

    public function testRunRetriesWhenTheDatabaseIsLockedByAnotherProcess(): void
    {
        $migrateCommand = new LockedMigrateCommandStub(numberOfLockedRuns: 1);
        $migrationRunner = $this->buildMigrationRunner($migrateCommand);

        $migrationRunner->run($this->output);

        $this->assertSame(2, $migrateCommand->numberOfRuns);
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:09'), $this->clock->now());
        $this->assertStringContainsString('The database is locked by another process, retrying', $this->output->fetch());
    }

    public function testRunThrowsWhenTheDatabaseStaysLocked(): void
    {
        $migrateCommand = new LockedMigrateCommandStub(numberOfLockedRuns: PHP_INT_MAX);
        $migrationRunner = $this->buildMigrationRunner($migrateCommand);

        $this->expectExceptionObject(new CouldNotRunMigrations('The database was locked by another process, migrations could not run.'));

        try {
            $migrationRunner->run($this->output);
        } finally {
            $this->assertSame(3, $migrateCommand->numberOfRuns);
            $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:14'), $this->clock->now());
        }
    }

    public function testRunThrowsWhenMigrationsFail(): void
    {
        $migrateCommand = new LockedMigrateCommandStub(
            numberOfLockedRuns: 0,
            exitCode: Command::FAILURE,
        );
        $migrationRunner = $this->buildMigrationRunner($migrateCommand);

        $this->expectExceptionObject(new CouldNotRunMigrations(''));

        try {
            $migrationRunner->run($this->output);
        } finally {
            $this->assertSame(1, $migrateCommand->numberOfRuns);
            $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:04'), $this->clock->now());
        }
    }

    private function buildMigrationRunner(Command $migrateCommand): DoctrineMigrationRunner
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand($migrateCommand);
        ConsoleApplication::setApplication($application);

        return new DoctrineMigrationRunner(
            new MigrationSquashHandler($this->getConnection()),
            $this->clock,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = PausedClock::fromString('2023-10-17 16:15:04');
        $this->output = new BufferedOutput();
    }
}

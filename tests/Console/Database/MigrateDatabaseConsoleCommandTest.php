<?php

namespace App\Tests\Console\Database;

use App\Console\Database\MigrateDatabaseConsoleCommand;
use App\Infrastructure\Doctrine\Migrations\CouldNotRunMigrations;
use App\Infrastructure\Doctrine\Migrations\MigrationsOutdated;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\Doctrine\Migrations\MigrationRunnerStub;
use App\Tests\Infrastructure\Doctrine\Migrations\VoidMigrationRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateDatabaseConsoleCommandTest extends ConsoleCommandTestCase
{
    private MigrateDatabaseConsoleCommand $migrateDatabaseConsoleCommand;

    public function testExecuteReturnsSuccessWhenMigrationsRun(): void
    {
        $command = $this->getCommandInApplication('app:db:migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteReturnsFailureWhenMigrationsOutdated(): void
    {
        $this->migrateDatabaseConsoleCommand = new MigrateDatabaseConsoleCommand(
            new MigrationRunnerStub(runException: new MigrationsOutdated()),
        );

        $command = $this->getCommandInApplication('app:db:migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Database migrations have been squashed', $commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenMigrationsCouldNotRun(): void
    {
        $this->migrateDatabaseConsoleCommand = new MigrateDatabaseConsoleCommand(
            new MigrationRunnerStub(runException: new CouldNotRunMigrations('Boom')),
        );

        $command = $this->getCommandInApplication('app:db:migrate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Boom', $commandTester->getDisplay());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateDatabaseConsoleCommand = new MigrateDatabaseConsoleCommand(
            new VoidMigrationRunner(),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->migrateDatabaseConsoleCommand;
    }
}

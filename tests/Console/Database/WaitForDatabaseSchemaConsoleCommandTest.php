<?php

namespace App\Tests\Console\Database;

use App\Console\Database\WaitForDatabaseSchemaConsoleCommand;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\Doctrine\Migrations\MigrationRunnerStub;
use App\Tests\Infrastructure\Time\Sleep\NullSleep;
use Doctrine\DBAL\Driver\SQLite3\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WaitForDatabaseSchemaConsoleCommandTest extends ConsoleCommandTestCase
{
    private WaitForDatabaseSchemaConsoleCommand $waitForDatabaseSchemaConsoleCommand;
    private NullSleep $sleep;

    public function testExecuteReturnsSuccessWhenSchemaIsUpToDate(): void
    {
        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(0, $this->sleep->getTotalSleptInSeconds());
    }

    public function testExecuteWaitsUntilTheMigratingContainerIsDone(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(numberOfChecksBeforeLatestVersion: 3),
            $this->sleep,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(6, $this->sleep->getTotalSleptInSeconds());
    }

    public function testExecuteKeepsWaitingWhenTheDatabaseCannotBeReachedYet(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(
                firstCheckException: new ConnectionException(new Exception('unable to open database file'), null),
            ),
            $this->sleep,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(2, $this->sleep->getTotalSleptInSeconds());
    }

    public function testExecuteReturnsFailureWhenSchemaDoesNotBecomeUpToDate(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(numberOfChecksBeforeLatestVersion: PHP_INT_MAX),
            $this->sleep,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertSame(300, $this->sleep->getTotalSleptInSeconds());
        $this->assertStringContainsString('still not up to date after 300 seconds', $commandTester->getDisplay());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sleep = new NullSleep();
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(),
            $this->sleep,
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->waitForDatabaseSchemaConsoleCommand;
    }
}

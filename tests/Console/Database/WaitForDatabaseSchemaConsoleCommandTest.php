<?php

namespace App\Tests\Console\Database;

use App\Console\Database\WaitForDatabaseSchemaConsoleCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\Doctrine\Migrations\MigrationRunnerStub;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Doctrine\DBAL\Driver\SQLite3\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WaitForDatabaseSchemaConsoleCommandTest extends ConsoleCommandTestCase
{
    private WaitForDatabaseSchemaConsoleCommand $waitForDatabaseSchemaConsoleCommand;
    private PausedClock $clock;

    public function testExecuteReturnsSuccessWhenSchemaIsUpToDate(): void
    {
        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:04'), $this->clock->now());
    }

    public function testExecuteWaitsUntilTheMigratingContainerIsDone(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(numberOfChecksBeforeLatestVersion: 3),
            $this->clock,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:10'), $this->clock->now());
    }

    public function testExecuteKeepsWaitingWhenTheDatabaseCannotBeReachedYet(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(
                firstCheckException: new ConnectionException(new Exception('unable to open database file'), null),
            ),
            $this->clock,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:06'), $this->clock->now());
    }

    public function testExecuteReturnsFailureWhenSchemaDoesNotBecomeUpToDate(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(numberOfChecksBeforeLatestVersion: PHP_INT_MAX),
            $this->clock,
        );

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:20:04'), $this->clock->now());
        $this->assertStringContainsString('still not up to date after 300 seconds', $commandTester->getDisplay());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = PausedClock::fromString('2023-10-17 16:15:04');
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new MigrationRunnerStub(),
            $this->clock,
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->waitForDatabaseSchemaConsoleCommand;
    }
}

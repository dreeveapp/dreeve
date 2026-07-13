<?php

namespace App\Tests\Console\Database;

use App\Console\Database\WaitForDatabaseSchemaConsoleCommand;
use App\Infrastructure\Doctrine\Migrations\MigrationRunner;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\Time\Sleep\NullSleep;
use Doctrine\DBAL\Driver\SQLite3\Exception;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class WaitForDatabaseSchemaConsoleCommandTest extends ConsoleCommandTestCase
{
    private WaitForDatabaseSchemaConsoleCommand $waitForDatabaseSchemaConsoleCommand;
    private NullSleep $sleep;

    public function testExecuteReturnsSuccessWhenSchemaIsUpToDate(): void
    {
        $this->buildCommandThatIsAtLatestVersionAfter(0);

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(0, $this->sleep->getTotalSleptInSeconds());
    }

    public function testExecuteWaitsUntilTheMigratingContainerIsDone(): void
    {
        $this->buildCommandThatIsAtLatestVersionAfter(3);

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertSame(6, $this->sleep->getTotalSleptInSeconds());
    }

    public function testExecuteKeepsWaitingWhenTheDatabaseCannotBeReachedYet(): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new class implements MigrationRunner {
                private int $numberOfChecks = 0;

                public function run(OutputInterface $output): void
                {
                }

                public function isAtLatestVersion(): bool
                {
                    if (1 === ++$this->numberOfChecks) {
                        throw new ConnectionException(new Exception('unable to open database file'), null);
                    }

                    return true;
                }
            },
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
        $this->buildCommandThatIsAtLatestVersionAfter(PHP_INT_MAX);

        $command = $this->getCommandInApplication('app:db:wait-for-schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertSame(300, $this->sleep->getTotalSleptInSeconds());
        $this->assertStringContainsString('still not up to date after 300 seconds', $commandTester->getDisplay());
    }

    private function buildCommandThatIsAtLatestVersionAfter(int $numberOfChecks): void
    {
        $this->waitForDatabaseSchemaConsoleCommand = new WaitForDatabaseSchemaConsoleCommand(
            new class($numberOfChecks) implements MigrationRunner {
                private int $numberOfChecks = 0;

                public function __construct(
                    private readonly int $numberOfChecksBeforeLatestVersion,
                ) {
                }

                public function run(OutputInterface $output): void
                {
                }

                public function isAtLatestVersion(): bool
                {
                    return $this->numberOfChecks++ >= $this->numberOfChecksBeforeLatestVersion;
                }
            },
            $this->sleep,
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->sleep = new NullSleep();
        $this->buildCommandThatIsAtLatestVersionAfter(0);
    }

    protected function getConsoleCommand(): Command
    {
        return $this->waitForDatabaseSchemaConsoleCommand;
    }
}

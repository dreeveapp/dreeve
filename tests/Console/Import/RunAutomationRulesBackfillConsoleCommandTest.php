<?php

namespace App\Tests\Console\Import;

use App\Application\AppStatusChecker;
use App\Console\Import\RunAutomationRulesBackfillConsoleCommand;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\AutomationRulesBackfillRequest;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\FileSystem\PermissionChecker;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\FileSystem\UnwritablePermissionChecker;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunAutomationRulesBackfillConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private const string TODAY = '2025-12-04';

    private RunAutomationRulesBackfillConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testRunsAQueuedBackfill(): void
    {
        new AutomationRulesBackfillQueue($this->keyValueStore)->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray([ActivityId::fromUnprefixed('a')]),
        ));

        $command = $this->getCommandInApplication(RunAutomationRulesBackfillConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testSkipsWhenNoBackfillIsQueued(): void
    {
        $command = $this->getCommandInApplication(RunAutomationRulesBackfillConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString('No automation rules backfill queued...', $commandTester->getDisplay());
    }

    public function testReturnsEarlyWhenImportModeIsStrava(): void
    {
        new AutomationRulesBackfillQueue($this->keyValueStore)->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray([ActivityId::fromUnprefixed('a')]),
        ));

        $command = $this->buildCommand($commandBus = new SpyCommandBus(), importMode: ImportMode::STRAVA_API);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunAutomationRulesBackfillConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($commandBus->getDispatchedCommands());
        $this->assertStringContainsString('Automation rules are only available in file import mode', $commandTester->getDisplay());
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $queue = new AutomationRulesBackfillQueue($this->keyValueStore);
        $queue->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray([ActivityId::fromUnprefixed('a')]),
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication(RunAutomationRulesBackfillConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Postponing backfill, another process is importing data.',
            $commandTester->getDisplay(),
        );
        $this->assertTrue($queue->isQueued(), 'A postponed backfill stays queued for the next cycle.');
    }

    public function testShowsErrorAndReleasesLockWhenWriteAccessFails(): void
    {
        new AutomationRulesBackfillQueue($this->keyValueStore)->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray([ActivityId::fromUnprefixed('a')]),
        ));

        $command = $this->buildCommand(
            $commandBus = new SpyCommandBus(),
            permissionChecker: new UnwritablePermissionChecker(),
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunAutomationRulesBackfillConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString(
            'Make sure the container has write permissions to "storage/database" and "storage/files" on the host system',
            $commandTester->getDisplay(),
        );
        $this->assertEmpty($commandBus->getDispatchedCommands());

        $row = $this->getConnection()->fetchOne(
            'SELECT `value` FROM KeyValue WHERE `key` = :key',
            ['key' => 'lock.importData']
        );
        $this->assertFalse($row, 'Expected the mutex lock to be released');
    }

    public function testLogsReleasesLockAndRethrowsWhenTheBackfillFails(): void
    {
        new AutomationRulesBackfillQueue($this->keyValueStore)->queue(AutomationRulesBackfillRequest::fromState(
            AutomationRuleIds::fromArray([AutomationRuleId::fromUnprefixed('1')]),
            ActivityIds::fromArray([ActivityId::fromUnprefixed('a')]),
        ));

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects($this->once())->method('dispatch')->willThrowException(new \RuntimeException('OH NO ERROR'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('OH NO ERROR');

        $command = $this->buildCommand($commandBus, logger: $logger);

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunAutomationRulesBackfillConsoleCommand::NAME));

        $thrown = null;
        try {
            $commandTester->execute(['command' => $command->getName()]);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertSame('OH NO ERROR', $thrown?->getMessage());
        $row = $this->getConnection()->fetchOne(
            'SELECT `value` FROM KeyValue WHERE `key` = :key',
            ['key' => 'lock.importData']
        );
        $this->assertFalse($row, 'Expected the mutex lock to be released');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->command = $this->buildCommand($this->commandBus = new SpyCommandBus());
    }

    private function buildCommand(
        CommandBus $commandBus,
        PermissionChecker $permissionChecker = new SuccessfulPermissionChecker(),
        ImportMode $importMode = ImportMode::FILES,
        LoggerInterface $logger = new NullLogger(),
    ): RunAutomationRulesBackfillConsoleCommand {
        return new RunAutomationRulesBackfillConsoleCommand(
            commandBus: $commandBus,
            appStatusChecker: new AppStatusChecker($permissionChecker),
            backfillQueue: new AutomationRulesBackfillQueue($this->getContainer()->get(KeyValueStore::class)),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            logger: $logger,
            importMode: $importMode,
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

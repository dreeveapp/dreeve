<?php

namespace App\Tests\Console\Import;

use App\Console\Import\RefreshActivitiesFromFilesConsoleCommand;
use App\Domain\Import\ImportMode;
use App\Domain\Import\RefreshFromFiles\ActivityFieldsToRefresh;
use App\Domain\Import\RefreshFromFiles\RefreshableActivityField;
use App\Domain\Import\RefreshFromFiles\RefreshActivitiesFromFiles;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshActivitiesFromFilesConsoleCommandTest extends ConsoleCommandTestCase
{
    private const string TODAY = '2025-12-04';

    private RefreshActivitiesFromFilesConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private ActivityFieldsToRefresh $activityFieldsToRefresh;

    public function testRefreshesQueuedFields(): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $command = $this->getCommandInApplication(RefreshActivitiesFromFilesConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(RefreshActivitiesFromFiles::class, $dispatchedCommands[0]);
        $this->assertFalse(
            $this->getConnection()->fetchOne('SELECT `value` FROM KeyValue WHERE `key` = :key', ['key' => 'lock.importData']),
            'Expected the mutex lock to be released',
        );
    }

    public function testSkipsWhenNothingIsQueued(): void
    {
        $command = $this->getCommandInApplication(RefreshActivitiesFromFilesConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString('No activity fields to refresh...', $commandTester->getDisplay());
    }

    public function testReturnsEarlyWhenImportModeIsStrava(): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $command = new RefreshActivitiesFromFilesConsoleCommand(
            commandBus: $commandBus = new SpyCommandBus(),
            activityFieldsToRefresh: $this->activityFieldsToRefresh,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            logger: new NullLogger(),
            importMode: ImportMode::STRAVA_API,
        );
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RefreshActivitiesFromFilesConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Refreshing activities from files is only available in file import mode',
            $commandTester->getDisplay(),
        );
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication(RefreshActivitiesFromFilesConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Postponing refresh until the next daemon start, another process is importing data.',
            $commandTester->getDisplay(),
        );
        $this->assertTrue($this->activityFieldsToRefresh->hasAny());
    }

    public function testLogsReleasesLockAndRethrowsWhenTheRefreshFails(): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects($this->once())->method('dispatch')->willThrowException(new \RuntimeException('OH NO ERROR'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('OH NO ERROR');

        $command = new RefreshActivitiesFromFilesConsoleCommand(
            commandBus: $commandBus,
            activityFieldsToRefresh: $this->activityFieldsToRefresh,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            logger: $logger,
            importMode: ImportMode::FILES,
        );
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RefreshActivitiesFromFilesConsoleCommand::NAME));

        $thrown = null;
        try {
            $commandTester->execute(['command' => $command->getName()]);
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertSame('OH NO ERROR', $thrown?->getMessage());
        $this->assertFalse(
            $this->getConnection()->fetchOne('SELECT `value` FROM KeyValue WHERE `key` = :key', ['key' => 'lock.importData']),
            'Expected the mutex lock to be released',
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityFieldsToRefresh = new ActivityFieldsToRefresh($this->getContainer()->get(KeyValueStore::class));
        $this->command = new RefreshActivitiesFromFilesConsoleCommand(
            commandBus: $this->commandBus = new SpyCommandBus(),
            activityFieldsToRefresh: $this->activityFieldsToRefresh,
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            logger: new NullLogger(),
            importMode: ImportMode::FILES,
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

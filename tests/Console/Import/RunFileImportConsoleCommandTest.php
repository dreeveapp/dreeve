<?php

namespace App\Tests\Console\Import;

use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Console\Import\RunFileImportConsoleCommand;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Domain\Import\WatchDirectory;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\FileSystem\PermissionChecker;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\FileSystem\UnwritablePermissionChecker;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\Infrastructure\Time\ResourceUsage\FixedResourceUsage;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunFileImportConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private const string TODAY = '2025-12-04';

    private RunFileImportConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private FilesystemOperator $watchStorage;
    private KeyValueStore $keyValueStore;

    public function testRunsWhenFilesArePresent(): void
    {
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $command = $this->getCommandInApplication(RunFileImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testIgnoresTheLegacyImportAndBuildOptions(): void
    {
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $withoutOptions = $this->runWithOptions([]);
        $withOptions = $this->runWithOptions(['--import' => true, '--build' => true]);

        $this->assertSame($withoutOptions, $withOptions);
    }

    public function testStillCalculatesMetricsWhenThereAreNoFiles(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: SettingsGroup::INTEGRATIONS->keyValueKey(),
            value: Value::fromString(Json::encode(['notifications' => ['notifyOnSuccessfulBuild' => true]])),
        ));

        $command = $this->getCommandInApplication(RunFileImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('No files left to process...', $commandTester->getDisplay());

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(CalculateActivityMetrics::class, $dispatchedCommands[0]);
    }

    public function testDoesNotSendANotificationWhenTheSuccessfulImportNotificationIsDisabled(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $this->keyValueStore->save(KeyValue::fromState(
            key: SettingsGroup::INTEGRATIONS->keyValueKey(),
            value: Value::fromString(Json::encode(['notifications' => ['notifyOnSuccessfulBuild' => false]])),
        ));

        $command = $this->getCommandInApplication(RunFileImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertNotEmpty($dispatchedCommands);
        $this->assertEmpty(array_filter(
            $dispatchedCommands,
            static fn (DomainCommand $dispatchedCommand): bool => $dispatchedCommand instanceof SendNotification,
        ));
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication(RunFileImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Postponing file import, another process is importing data.',
            $commandTester->getDisplay(),
        );
    }

    public function testReturnsEarlyWhenImportModeIsStrava(): void
    {
        $command = $this->buildCommand(new SpyCommandBus(), importMode: ImportMode::STRAVA_API);

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunFileImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('Cannot import files. IMPORT_MODE=stravaApi', $commandTester->getDisplay());
    }

    public function testShowsErrorAndReleasesLockWhenWriteAccessFails(): void
    {
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $command = $this->buildCommand(
            $commandBus = new SpyCommandBus(),
            permissionChecker: new UnwritablePermissionChecker(),
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunFileImportConsoleCommand::NAME));
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

    public function testLogsReleasesLockAndRethrowsWhenImportFails(): void
    {
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects($this->once())->method('dispatch')->willThrowException(new \RuntimeException('OH NO ERROR'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('OH NO ERROR');

        $command = $this->buildCommand($commandBus, logger: $logger);

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunFileImportConsoleCommand::NAME));

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

        $this->watchStorage = $this->getContainer()->get('default.storage');
        $this->watchStorage->deleteDirectory('watch');
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        $this->command = $this->buildCommand($this->commandBus = new SpyCommandBus());
    }

    /**
     * @param array<string, bool> $options
     */
    private function runWithOptions(array $options): string
    {
        $command = $this->buildCommand($commandBus = new SpyCommandBus());

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunFileImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName(), ...$options]);

        return Json::encode($commandBus->getDispatchedCommands());
    }

    private function buildCommand(
        CommandBus $commandBus,
        PermissionChecker $permissionChecker = new SuccessfulPermissionChecker(),
        ImportMode $importMode = ImportMode::FILES,
        LoggerInterface $logger = new NullLogger(),
    ): RunFileImportConsoleCommand {
        return new RunFileImportConsoleCommand(
            commandBus: $commandBus,
            appStatusChecker: new AppStatusChecker($permissionChecker),
            watchDirectory: $this->getContainer()->get(WatchDirectory::class),
            resourceUsage: new FixedResourceUsage(),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            appUrl: AppUrl::fromString('http://localhost'),
            logger: $logger,
            importMode: $importMode,
            settingsRepository: $this->getContainer()->get(KeyValueBasedSettingsRepository::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

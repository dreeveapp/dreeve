<?php

namespace App\Tests\Console\Daemon;

use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Console\Daemon\RunStravaImportConsoleCommand;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\DomainCommand;
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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RunStravaImportConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private const string TODAY = '2025-12-04';

    private RunStravaImportConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testRun(): void
    {
        $command = $this->getCommandInApplication(RunStravaImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testRunWithRestrictToActivityIds(): void
    {
        $command = $this->getCommandInApplication(RunStravaImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            RunStravaImportConsoleCommand::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT => 'activity-1,activity-2',
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testIgnoresTheLegacyImportAndBuildOptions(): void
    {
        $withoutOptions = $this->runWithOptions([]);
        $withOptions = $this->runWithOptions(['--import' => true, '--build' => true]);

        $this->assertSame($withoutOptions, $withOptions);
    }

    public function testDoesNotSendANotificationWhenTheSuccessfulImportNotificationIsDisabled(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: SettingsGroup::INTEGRATIONS->keyValueKey(),
            value: Value::fromString(Json::encode(['notifications' => ['notifyOnSuccessfulBuild' => false]])),
        ));

        $command = $this->getCommandInApplication(RunStravaImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertNotEmpty($dispatchedCommands);
        $this->assertEmpty(array_filter(
            $dispatchedCommands,
            static fn (DomainCommand $dispatchedCommand): bool => $dispatchedCommand instanceof SendNotification,
        ));
    }

    public function testReturnsEarlyInFileMode(): void
    {
        $command = $this->buildCommand(
            commandBus: $this->commandBus,
            importMode: ImportMode::FILES,
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunStravaImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString('Cannot import files. IMPORT_MODE=files', $commandTester->getDisplay());
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication(RunStravaImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Postponing Strava import, another process is importing data.',
            $commandTester->getDisplay(),
        );
    }

    public function testLogsAndRethrowsWhenImportFails(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('OH NO ERROR'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('OH NO ERROR');

        $command = $this->buildCommand(
            commandBus: $commandBus,
            logger: $logger,
        );

        $this->expectExceptionObject(new \RuntimeException('OH NO ERROR'));

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunStravaImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);
    }

    public function testReturnsEarlyWhenAppIsNotReady(): void
    {
        $command = $this->buildCommand(
            commandBus: $this->commandBus,
            appStatusChecker: new AppStatusChecker(new UnwritablePermissionChecker()),
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunStravaImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString(
            'Make sure the container has write permissions to "storage/database" and "storage/files" on the host system',
            $commandTester->getDisplay(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->command = $this->buildCommand(commandBus: $this->commandBus = new SpyCommandBus());
    }

    /**
     * @param array<string, bool> $options
     */
    private function runWithOptions(array $options): string
    {
        $command = $this->buildCommand(commandBus: $commandBus = new SpyCommandBus());

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find(RunStravaImportConsoleCommand::NAME));
        $commandTester->execute(['command' => $command->getName(), ...$options]);

        return Json::encode($commandBus->getDispatchedCommands());
    }

    private function buildCommand(
        CommandBus $commandBus,
        ImportMode $importMode = ImportMode::STRAVA_API,
        ?LoggerInterface $logger = null,
        ?AppStatusChecker $appStatusChecker = null,
    ): RunStravaImportConsoleCommand {
        return new RunStravaImportConsoleCommand(
            commandBus: $commandBus,
            resourceUsage: new FixedResourceUsage(),
            strava: $this->getContainer()->get(Strava::class),
            logger: $logger ?? new NullLogger(),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            appStatusChecker: $appStatusChecker ?? new AppStatusChecker(new SuccessfulPermissionChecker()),
            appUrl: AppUrl::fromString('http://localhost'),
            importMode: $importMode,
            settingsRepository: $this->getContainer()->get(KeyValueBasedSettingsRepository::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

<?php

namespace App\Tests\Console\Daemon;

use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\RebuildStatus;
use App\Console\Daemon\RunStravaImportAndBuildAppConsoleCommand;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
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

class RunStravaImportAndBuildAppConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private const string TODAY = '2025-12-04';

    private RunStravaImportAndBuildAppConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testRun(): void
    {
        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testRunWithRestrictToActivityIds(): void
    {
        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            RunStravaImportAndBuildAppConsoleCommand::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT => 'activity-1,activity-2',
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testImportsOnlyWhenImportOptionIsSet(): void
    {
        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::IMPORT_OPTION => true,
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testBuildsOnlyWhenBuildOptionIsSet(): void
    {
        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::BUILD_OPTION => true,
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testBuildIfRequiredSkipsWhenAlreadyBuiltTodayAndNothingPending(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILT_ON,
            value: Value::fromString(self::TODAY),
        ));

        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::BUILD_OPTION => true,
            '--'.RunStravaImportAndBuildAppConsoleCommand::IF_REQUIRED_OPTION => true,
        ]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString('Nothing to build...', $commandTester->getDisplay());
    }

    public function testBuildIfRequiredStillBuildsWhenImportingEvenIfNoRebuildIsRequired(): void
    {
        // Already built today and nothing pending, so aRebuildIsRequired is false;
        // the build must still happen because the import phase runs (--if-required alone
        // enables both phases).
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILT_ON,
            value: Value::fromString(self::TODAY),
        ));

        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::IF_REQUIRED_OPTION => true,
        ]);

        $this->assertStringNotContainsString('Nothing to build...', $commandTester->getDisplay());
        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testBuildIfRequiredBuildsAndClearsForceRebuildWhenPending(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILT_ON,
            value: Value::fromString(self::TODAY),
        ));
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::FORCE_REBUILD,
            value: Value::fromString('1'),
        ));

        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::BUILD_OPTION => true,
            '--'.RunStravaImportAndBuildAppConsoleCommand::IF_REQUIRED_OPTION => true,
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
        $this->assertSame(self::TODAY, (string) $this->keyValueStore->find(Key::APP_LAST_BUILT_ON));

        $this->expectExceptionObject(new EntityNotFound('KeyValue "forceRebuild" not found'));
        $this->keyValueStore->find(Key::FORCE_REBUILD);
    }

    public function testBuildAlwaysBuildsWithoutIfRequiredEvenWhenAlreadyBuiltToday(): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILT_ON,
            value: Value::fromString(self::TODAY),
        ));

        $command = $this->getCommandInApplication('app:cron:run-strava-import');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--'.RunStravaImportAndBuildAppConsoleCommand::BUILD_OPTION => true,
        ]);

        $this->assertMatchesJsonSnapshot(Json::encode($this->commandBus->getDispatchedCommands()));
    }

    public function testReturnsEarlyInFileMode(): void
    {
        $command = $this->buildCommand(
            commandBus: $this->commandBus,
            importMode: ImportMode::FILES,
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:cron:run-strava-import'));
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertEmpty($this->commandBus->getDispatchedCommands());
        $this->assertStringContainsString('Cannot import files. IMPORT_MODE=files', $commandTester->getDisplay());
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importDataOrBuildApp', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication('app:cron:run-strava-import');
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

        $commandTester = new CommandTester($application->find('app:cron:run-strava-import'));
        $commandTester->execute(['command' => $command->getName()]);
    }

    public function testReturnsEarlyWhenAppIsNotReady(): void
    {
        $command = $this->buildCommand(
            commandBus: $this->commandBus,
            appStatusChecker: new AppStatusChecker(
                $this->getContainer()->get(SettingsRepository::class),
                $this->getContainer()->get(ActivityIdRepository::class),
                new UnwritablePermissionChecker(),
            ),
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:cron:run-strava-import'));
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

    private function buildCommand(
        CommandBus $commandBus,
        ImportMode $importMode = ImportMode::STRAVA_API,
        ?LoggerInterface $logger = null,
        ?AppStatusChecker $appStatusChecker = null,
    ): RunStravaImportAndBuildAppConsoleCommand {
        return new RunStravaImportAndBuildAppConsoleCommand(
            commandBus: $commandBus,
            resourceUsage: new FixedResourceUsage(),
            strava: $this->getContainer()->get(Strava::class),
            logger: $logger ?? new NullLogger(),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA_OR_BUILD_APP,
            ),
            appStatusChecker: $appStatusChecker ?? new AppStatusChecker(
                $this->getContainer()->get(SettingsRepository::class),
                $this->getContainer()->get(ActivityIdRepository::class),
                new SuccessfulPermissionChecker(),
            ),
            appUrl: AppUrl::fromString('http://localhost'),
            importMode: $importMode,
            keyValueStore: $this->keyValueStore,
            rebuildStatus: new RebuildStatus($this->keyValueStore),
            clock: PausedClock::fromString(self::TODAY),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

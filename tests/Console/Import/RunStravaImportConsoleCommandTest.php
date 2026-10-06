<?php

namespace App\Tests\Console\Import;

use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Application\Import\ImportSuccessfulNotification;
use App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion\DeleteActivitiesMarkedForDeletion;
use App\Application\Import\StravaImport\ImportActivities\ImportActivities;
use App\Application\Import\StravaImport\ImportChallenges\ImportChallenges;
use App\Application\Import\StravaImport\ImportGear\ImportGear;
use App\Application\Import\StravaImport\ImportSegments\ImportSegments;
use App\Application\Import\StravaImport\ProcessRawActivityData\ProcessRawActivityData;
use App\Console\Import\RunStravaImportConsoleCommand;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\DomainCommand;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RunStravaImportConsoleCommandTest extends ConsoleCommandTestCase
{
    private const string TODAY = '2025-12-04';

    private RunStravaImportConsoleCommand $command;
    private SpyCommandBus $commandBus;
    private DbalSettingsRepository $settingsRepository;

    /**
     * @param array<string, string> $arguments
     * @param list<string>          $expectedRestrictToActivityIds
     */
    #[DataProvider('provideRuns')]
    public function testRun(array $arguments, array $expectedRestrictToActivityIds): void
    {
        $command = $this->getCommandInApplication(RunStravaImportConsoleCommand::NAME);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), ...$arguments]);

        $dispatchedCommands = $this->commandBus->getDispatchedCommands();
        $this->assertSame(
            [
                ImportActivities::class,
                ImportGear::class,
                ProcessRawActivityData::class,
                ImportSegments::class,
                ImportChallenges::class,
                CalculateActivityMetrics::class,
                DeleteActivitiesMarkedForDeletion::class,
                SendNotification::class,
            ],
            array_map(get_class(...), $dispatchedCommands),
        );
        foreach ([$dispatchedCommands[0], $dispatchedCommands[1]] as $dispatchedCommand) {
            $this->assertSame(
                $expectedRestrictToActivityIds,
                array_map(strval(...), $dispatchedCommand->getRestrictToActivityIds()->toArray()),
            );
        }
        $this->assertEquals(
            new SendNotification(
                title: 'Import successful',
                message: 'New import of your stats was successful in 10s',
                tags: ['+1'],
                actionUrl: AppUrl::fromString('http://localhost'),
            ),
            $dispatchedCommands[7],
        );
    }

    public static function provideRuns(): iterable
    {
        yield 'every activity' => [[], []];
        yield 'restricted to activity ids' => [[RunStravaImportConsoleCommand::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT => 'activity-1,activity-2'], ['activity-1', 'activity-2']];
    }

    public function testIgnoresTheLegacyImportAndBuildOptions(): void
    {
        $withoutOptions = $this->runWithOptions([]);
        $withOptions = $this->runWithOptions(['--import' => true, '--build' => true]);

        $this->assertSame($withoutOptions, $withOptions);
    }

    public function testDoesNotSendANotificationWhenTheSuccessfulImportNotificationIsDisabled(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::INTEGRATIONS, [
            'notifications' => ['notifyOnSuccessfulBuild' => false],
        ]);

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

        $this->settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);

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
            importSuccessfulNotification: new ImportSuccessfulNotification(
                appUrl: AppUrl::fromString('http://localhost'),

                urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
            ),
            importMode: $importMode,
            settingsRepository: $this->getContainer()->get(DbalSettingsRepository::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

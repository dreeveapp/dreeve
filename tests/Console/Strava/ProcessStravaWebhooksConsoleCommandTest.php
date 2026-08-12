<?php

namespace App\Tests\Console\Strava;

use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Console\Import\RunStravaImportConsoleCommand;
use App\Console\Strava\ProcessStravaWebhooksConsoleCommand;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Domain\Strava\Webhook\WebhookAspectType;
use App\Domain\Strava\Webhook\WebhookEvent;
use App\Domain\Strava\Webhook\WebhookEventRepository;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Serialization\Json;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\Infrastructure\Time\ResourceUsage\FixedResourceUsage;
use Psr\Log\NullLogger;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ProcessStravaWebhooksConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private const string TODAY = '2025-12-04';

    private ProcessStravaWebhooksConsoleCommand $command;

    public function testProcessesWebhooksAndDelegatesToStravaImport(): void
    {
        foreach ([
            ['1', WebhookAspectType::CREATE],
            ['2', WebhookAspectType::UPDATE],
            ['3', WebhookAspectType::DELETE],
        ] as [$objectId, $aspectType]) {
            $this->getContainer()->get(WebhookEventRepository::class)->add(WebhookEvent::create(
                objectId: $objectId,
                objectType: 'activity',
                aspectType: $aspectType,
                payload: [],
            ));
        }

        $command = $this->getCommandInApplication('app:strava:webhooks-process');
        $command->getApplication()->addCommand($this->buildStravaImportCommand($spyCommandBus = new SpyCommandBus()));

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertMatchesJsonSnapshot(Json::encode($spyCommandBus->getDispatchedCommands()));
    }

    public function testReturnsEarlyInFileMode(): void
    {
        $this->getContainer()->get(WebhookEventRepository::class)->add(WebhookEvent::create(
            objectId: '1',
            objectType: 'activity',
            aspectType: WebhookAspectType::CREATE,
            payload: [],
        ));

        $command = $this->buildWebhooksCommand(ImportMode::FILES);

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:strava:webhooks-process'));
        $statusCode = $commandTester->execute(['command' => $command->getName()]);

        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    public function testWhenThereAreNoWebhookEvents(): void
    {
        $command = $this->getCommandInApplication('app:strava:webhooks-process');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString('No webhook events left to process...', $commandTester->getDisplay());
    }

    public function testPostponesWhenLockIsAlreadyAcquired(): void
    {
        $this->getContainer()->get(WebhookEventRepository::class)->add(WebhookEvent::create(
            objectId: '1',
            objectType: 'activity',
            aspectType: WebhookAspectType::CREATE,
            payload: [],
        ));

        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test", "heartbeat": 1764806400}']
        );

        $command = $this->getCommandInApplication('app:strava:webhooks-process');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        $this->assertStringContainsString(
            'Postponing Strava import, another process is importing data.',
            $commandTester->getDisplay(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->command = $this->buildWebhooksCommand(ImportMode::STRAVA_API);
    }

    private function buildWebhooksCommand(ImportMode $importMode): ProcessStravaWebhooksConsoleCommand
    {
        return new ProcessStravaWebhooksConsoleCommand(
            webhookEventRepository: $this->getContainer()->get(WebhookEventRepository::class),
            activityRepository: $this->getContainer()->get(ActivityRepository::class),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            importMode: $importMode,
        );
    }

    private function buildStravaImportCommand(SpyCommandBus $commandBus): RunStravaImportConsoleCommand
    {
        return new RunStravaImportConsoleCommand(
            commandBus: $commandBus,
            resourceUsage: new FixedResourceUsage(),
            strava: $this->getContainer()->get(Strava::class),
            logger: new NullLogger(),
            mutex: new Mutex(
                connection: $this->getConnection(),
                clock: PausedClock::fromString(self::TODAY),
                lockName: LockName::IMPORT_DATA,
            ),
            appStatusChecker: new AppStatusChecker(new SuccessfulPermissionChecker()),
            appUrl: AppUrl::fromString('http://localhost'),
            importMode: ImportMode::STRAVA_API,
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );
    }

    protected function getConsoleCommand(): Command
    {
        return $this->command;
    }
}

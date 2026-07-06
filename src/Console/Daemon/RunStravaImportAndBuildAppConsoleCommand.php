<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Build\RunBuild\RunBuild;
use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion\DeleteActivitiesMarkedForDeletion;
use App\Application\Import\StravaImport\ImportActivities\ImportActivities;
use App\Application\Import\StravaImport\ImportChallenges\ImportChallenges;
use App\Application\Import\StravaImport\ImportGear\ImportGear;
use App\Application\Import\StravaImport\ImportSegments\ImportSegments;
use App\Application\Import\StravaImport\ProcessRawActivityData\ProcessRawActivityData;
use App\Application\RebuildStatus;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Import\ImportMode;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Strava\RateLimit\StravaRateLimits;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\Time\ResourceUsage\ResourceUsage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: RunStravaImportAndBuildAppConsoleCommand::NAME, description: 'Run Strava import')]
final class RunStravaImportAndBuildAppConsoleCommand extends Command
{
    use HandlesImportAndBuild;

    public const string NAME = 'app:cron:run-strava-import';
    public const string RESTRICT_TO_ACTIVITY_IDS_ARGUMENT = 'restrictToActivityIds';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly ResourceUsage $resourceUsage,
        private readonly Strava $strava,
        private readonly LoggerInterface $logger,
        private readonly Mutex $mutex,
        private readonly AppStatusChecker $appStatusChecker,
        private readonly AppUrl $appUrl,
        private readonly ImportMode $importMode,
        private readonly KeyValueStore $keyValueStore,
        private readonly RebuildStatus $rebuildStatus,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(self::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT, InputArgument::OPTIONAL);
        $this->addImportAndBuildOptions();
        $this->addIfRequiredOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        if (!$this->importMode->isStravaApi()) {
            $output->writeln('<comment>Cannot import files. IMPORT_MODE=files</comment>');

            return Command::SUCCESS;
        }

        $shouldImport = $this->resolvePhases($input)[self::IMPORT_OPTION];
        $today = $this->clock->getCurrentDateTimeImmutable()->format('Y-m-d');
        $appNeedsToBeBuilt = $this->appNeedsToBeBuilt(
            input: $input,
            keyValueStore: $this->keyValueStore,
            rebuildStatus: $this->rebuildStatus,
            today: $today
        );

        if (!$shouldImport && !$appNeedsToBeBuilt) {
            $output->writeln('Nothing to build...');

            return Command::SUCCESS;
        }

        $this->resourceUsage->startTimer();

        $restrictToActivityIds = null;
        if (!empty($input->getArgument(self::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT))) {
            $restrictToActivityIds = ActivityIds::fromArray(array_map(
                ActivityId::fromString(...),
                explode(',', (string) $input->getArgument(self::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT))
            ));
        }

        try {
            $this->mutex->acquireLock('runStravaImportAndBuildApp');
        } catch (LockIsAlreadyAcquired) {
            // Another process is importing data, postpone import.
            $output->writeln('<comment>Postponing Strava import, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
            if ($shouldImport) {
                $this->appStatusChecker->ensureIsReadyForStravaImport();

                $this->commandBus->dispatch(new ImportActivities(
                    output: $output,
                    restrictToActivityIds: $restrictToActivityIds
                ));
                $this->commandBus->dispatch(new ImportGear(
                    output: $output,
                    restrictToActivityIds: $restrictToActivityIds
                ));
                $this->commandBus->dispatch(new ProcessRawActivityData($output));
                $this->commandBus->dispatch(new ImportSegments($output));
                $this->commandBus->dispatch(new ImportChallenges($output));
                $this->commandBus->dispatch(new CalculateActivityMetrics($output));
                $this->commandBus->dispatch(new DeleteActivitiesMarkedForDeletion($output));

                if (($rateLimits = $this->strava->getRateLimit()) instanceof StravaRateLimits) {
                    $output->title('STRAVA API RATE LIMITS');
                    $output->listing([
                        sprintf('15 min rate: %s/%s', $rateLimits->getFifteenMinRateUsage(), $rateLimits->getFifteenMinRateLimit()),
                        sprintf('15 min read rate: %s/%s', $rateLimits->getFifteenMinReadRateUsage(), $rateLimits->getFifteenMinReadRateLimit()),
                        sprintf('daily rate: %s/%s', $rateLimits->getDailyRateUsage(), $rateLimits->getDailyRateLimit()),
                        sprintf('daily read rate: %s/%s', $rateLimits->getDailyReadRateUsage(), $rateLimits->getDailyReadRateLimit()),
                    ]);
                }
            }
            if ($appNeedsToBeBuilt) {
                $this->appStatusChecker->ensureIsReadyForBuild();

                $this->commandBus->dispatch(new RunBuild(
                    output: $output,
                ));

                $this->keyValueStore->save(KeyValue::fromState(
                    key: Key::APP_LAST_BUILT_ON,
                    value: Value::fromString($today),
                ));
                $this->keyValueStore->clear(Key::FORCE_REBUILD);
            }
        } catch (AppIsNotReady $e) {
            $this->mutex->releaseLock();
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->mutex->releaseLock();
            throw $e;
        }

        $this->mutex->releaseLock();

        $this->resourceUsage->stopTimer();
        $this->commandBus->dispatch(new SendNotification(
            title: 'Build successful',
            message: sprintf('New import and build of your stats was successful in %ss', $this->resourceUsage->getRunTimeInSeconds()),
            tags: ['+1'],
            actionUrl: $this->appUrl
        ));

        $output->writeln(sprintf(
            '<info>%s</info>',
            $this->resourceUsage->format(),
        ));

        return Command::SUCCESS;
    }
}

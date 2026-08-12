<?php

declare(strict_types=1);

namespace App\Console\Import;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Application\Import\StravaImport\DeleteActivitiesMarkedForDeletion\DeleteActivitiesMarkedForDeletion;
use App\Application\Import\StravaImport\ImportActivities\ImportActivities;
use App\Application\Import\StravaImport\ImportChallenges\ImportChallenges;
use App\Application\Import\StravaImport\ImportGear\ImportGear;
use App\Application\Import\StravaImport\ImportSegments\ImportSegments;
use App\Application\Import\StravaImport\ProcessRawActivityData\ProcessRawActivityData;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Import\ImportMode;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\RateLimit\StravaRateLimits;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\ResourceUsage\ResourceUsage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[WithMutex(lockName: LockName::IMPORT_DATA)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: RunStravaImportConsoleCommand::NAME, description: 'Run Strava import', aliases: ['app:cron:run-strava-import'])]
final class RunStravaImportConsoleCommand extends Command
{
    public const string NAME = 'app:import:strava';
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
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(self::RESTRICT_TO_ACTIVITY_IDS_ARGUMENT, InputArgument::OPTIONAL);
        $this->addOption('import', null, InputOption::VALUE_NONE);
        $this->addOption('build', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));

        if (!$this->importMode->isStravaApi()) {
            $output->writeln('<comment>Cannot import files. IMPORT_MODE=files</comment>');

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
            $this->mutex->acquireLock('runStravaImport');
        } catch (LockIsAlreadyAcquired) {
            // Another process is importing data, postpone import.
            $output->writeln('<comment>Postponing Strava import, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
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
        if ($this->settingsRepository->integrations()->shouldNotifyOnSuccessfulImport()) {
            $this->commandBus->dispatch(new SendNotification(
                title: 'Import successful',
                message: sprintf('New import of your stats was successful in %ss', $this->resourceUsage->getRunTimeInSeconds()),
                tags: ['+1'],
                actionUrl: $this->appUrl
            ));
        }

        $output->writeln(sprintf(
            '<info>%s</info>',
            $this->resourceUsage->format(),
        ));

        return Command::SUCCESS;
    }
}

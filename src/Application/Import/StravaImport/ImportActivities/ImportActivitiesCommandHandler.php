<?php

namespace App\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportStep;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityVisibility;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\RateLimit\StravaRateLimitHasBeenReached;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
final readonly class ImportActivitiesCommandHandler implements CommandHandler
{
    /**
     * @param iterable<ActivityImportStep> $steps
     */
    public function __construct(
        private Strava $strava,
        private ActivityRepository $activityRepository,
        private ActivityIdRepository $activityIdRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private SettingsRepository $settingsRepository,
        private Mutex $mutex,
        #[AutowireIterator('app.activity_import.pipeline_step')]
        private iterable $steps,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportActivities);
        $command->getOutput()->writeln('Importing activities...');

        $importSettings = $this->settingsRepository->import();
        $numberOfNewActivitiesToProcessPerImport = $importSettings->getNumberOfNewActivitiesToProcessPerImport();
        $sportTypesToImport = $importSettings->getSportTypesToImport();
        $activityVisibilitiesToImport = $importSettings->getActivityVisibilitiesToImport();
        $activitiesToSkipDuringImport = $importSettings->getActivitiesToSkipDuringImport();
        $skipActivitiesRecordedBefore = $importSettings->getSkipActivitiesRecordedBefore();

        $this->strava->setConsoleOutput($command->getOutput());

        $allActivityIds = $this->activityIdRepository->findAllImportedFromStravaApi();
        $activityIdsToDelete = array_combine(
            $allActivityIds->map(fn (ActivityId $activityId): string => (string) $activityId),
            $allActivityIds->toArray(),
        );

        try {
            if ($command->isFullImport()) {
                // No restriction on activity ids, we need to execute a full import.
                $stravaActivities = $this->strava->getActivities();
            } else {
                // Restriction on activity ids, we want to execute a partial import.
                $stravaActivities = array_map(
                    $this->strava->getActivity(...),
                    $command->getRestrictToActivityIds()->toArray()
                );
            }
        } catch (StravaRateLimitHasBeenReached $exception) {
            $command->getOutput()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

            return;
        } catch (ClientException|RequestException $exception) {
            $command->getOutput()->writeln(sprintf('<error>Strava API threw error: %s</error>', $exception->getMessage()));

            return;
        }

        $countTotalStravaActivitiesToImport = count($stravaActivities);
        if ($command->isFullImport()) {
            $command->getOutput()->writeln(
                sprintf('Status: %d out of %d activities imported', count($allActivityIds), $countTotalStravaActivitiesToImport)
            );
        }

        $delta = 1;
        foreach ($stravaActivities as $rawStravaData) {
            if (!SportType::tryFrom($rawStravaData['sport_type'])) {
                $command->getOutput()->writeln(sprintf(
                    '  => Sport type "%s" not supported yet. <a href="https://github.com/dreeveapp/dreeve/issues/new?assignees=robiningelbrecht&labels=new+feature&projects=&template=feature_request.md&title=Add+support+for+sport+type+%s>Open a new GitHub issue</a> to if you want support for this sport type',
                    $rawStravaData['sport_type'],
                    $rawStravaData['sport_type']));
                continue;
            }

            $sportType = SportType::from($rawStravaData['sport_type']);
            if (!$sportTypesToImport->has($sportType)) {
                continue;
            }

            $activityVisibility = ActivityVisibility::from($rawStravaData['visibility']);
            if (!$activityVisibilitiesToImport->has($activityVisibility)) {
                continue;
            }

            if ($skipActivitiesRecordedBefore?->isAfterOrOn(SerializableDateTime::createFromFormat(
                format: Activity::DATE_TIME_FORMAT,
                datetime: $rawStravaData['start_date_local'],
                timezone: SerializableTimezone::default(),
            ))) {
                continue;
            }

            $activityId = ActivityId::fromUnprefixed((string) $rawStravaData['id']);
            if ($activitiesToSkipDuringImport->has($activityId)) {
                continue;
            }

            try {
                $isNewActivity = !$this->activityRepository->exists($activityId);
                if ($isNewActivity && $command->isFullImport()) {
                    // When a partial import is triggered we fetch the activity from Strava beforehand.
                    // We only need to fetch activity details when running a full import.
                    $rawStravaData = $this->strava->getActivity($activityId);
                }

                $context = ActivityImportContext::create(
                    activityId: $activityId,
                    rawStravaData: $rawStravaData,
                    isNewActivity: $isNewActivity,
                );

                foreach ($this->steps as $step) {
                    $context = $step->process($context);
                }
            } catch (StravaRateLimitHasBeenReached $exception) {
                $command->getOutput()->writeln(sprintf('<error>%s</error>', $exception->getMessage()));

                return;
            } catch (ClientException|RequestException $exception) {
                $command->getOutput()->writeln(sprintf('<error>Strava API threw error: %s</error>', $exception->getMessage()));

                return;
            }

            $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');
            if ($context->isNewActivity()) {
                // We always expect "segment_efforts" to be set in raw data.
                // If not, something is really wrong. Abort import.
                if (!array_key_exists('segment_efforts', $context->getRawStravaData())) {
                    throw new \RuntimeException(sprintf('Activity %s is expected to include segment_efforts in the raw Strava data. This appears to be a regression introduced in a recent version. Please report this as a bug on GitHub.', $activityId->toUnprefixedString()));
                }
                $this->activityRepository->add(ActivityWithRawData::fromState(
                    activity: $activity,
                    rawData: $context->getRawStravaData()
                ));

                $numberOfNewActivitiesToProcessPerImport->increaseNumberOfProcessedActivities();
            } else {
                $this->activityRepository->update(ActivityWithRawData::fromState(
                    activity: $activity,
                    rawData: [
                        ...$this->activityRepository->findWithRawData($activity->getId())->getRawData(),
                        ...$rawStravaData,
                    ]
                ));
            }

            foreach ($context->getStreams() as $stream) {
                $this->activityStreamRepository->add($stream);
            }
            if ($context->streamsWereImported()) {
                $this->activityRepository->markActivityStreamsAsImported($activityId);
            }

            unset($activityIdsToDelete[(string) $context->getActivity()->getId()]);

            $command->getOutput()->writeln(sprintf(
                '  => [%d/%d] %s activity: "%s - %s"',
                $delta,
                $countTotalStravaActivitiesToImport,
                $context->isNewActivity() ? 'Imported' : 'Updated',
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y'))
            );

            if ($numberOfNewActivitiesToProcessPerImport->maxNumberProcessed()) {
                // Stop importing activities, we reached the max number to process for this batch.
                break;
            }

            $this->mutex->heartbeat();
            ++$delta;
        }

        if ($numberOfNewActivitiesToProcessPerImport->maxNumberProcessed()) {
            // Shortcut the process here to make sure no activities are deleted yet.
            return;
        }

        if (!$command->isFullImport()) {
            // Only delete activities during full imports to avoid accidental deletion of data.
            return;
        }

        if ([] === $activityIdsToDelete) {
            return;
        }

        if (count($activityIdsToDelete) === count($allActivityIds) && array_values($activityIdsToDelete) == $allActivityIds->toArray()) {
            throw new \RuntimeException('All activities appear to be marked for deletion. This seems like a configuration issue. Aborting to prevent data loss');
        }

        $this->activityRepository->markActivitiesForDeletion(ActivityIds::fromArray($activityIdsToDelete));

        foreach ($activityIdsToDelete as $activityId) {
            $activity = $this->activityRepository->find($activityId);

            $command->getOutput()->writeln(sprintf(
                '  => Activity "%s - %s" marked for deletion',
                $activity->getName(),
                $activity->getStartDate()->format('d-m-Y'))
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\ValueObject\String\Url;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsTaggedItem(priority: 60)]
final readonly class CalculateBestActivityEfforts implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
        private TranslatorInterface $translator,
        private AppUrl $appUrl,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated best efforts for 0 activities');

        $activityIdsWithoutBestEfforts = $this->activityBestEffortRepository->findActivityIdsThatNeedBestEffortsCalculation();

        /** @var array<string, ActivityBestEffort> $personalRecordsBeforeCalculation */
        $personalRecordsBeforeCalculation = [];
        $mostRecentStartDateTimeBeforeCalculation = null;
        if (!$activityIdsWithoutBestEfforts->isEmpty()) {
            $mostRecentStartDateTimeBeforeCalculation = $this->activityBestEffortRepository->findMostRecentStartDateTimeOfActivitiesWithBestEfforts();
            foreach ($this->activityBestEffortRepository->findPersonalRecords() as $personalRecord) {
                $personalRecordsBeforeCalculation[$this->buildPersonalRecordKey($personalRecord)] = $personalRecord;
            }
        }

        /** @var array<string, ActivityBestEffort> $fastestCalculatedBestEfforts */
        $fastestCalculatedBestEfforts = [];
        /** @var array<string, true> $activityIdsThatCanHoldNewPersonalRecords */
        $activityIdsThatCanHoldNewPersonalRecords = [];
        $activityWithBestEffortsCalculatedCount = 0;
        foreach ($activityIdsWithoutBestEfforts as $activityId) {
            $distanceStream = $this->activityStreamRepository->findOneByActivityAndStreamType($activityId, StreamType::DISTANCE);
            $timeStream = $this->activityStreamRepository->findOneByActivityAndStreamType($activityId, StreamType::TIME);

            $activity = $this->activityRepository->find($activityId);
            $distances = $distanceStream->getData();
            $time = $timeStream->getData();

            // Only activities newer than the ones already processed can hold a new personal record.
            // This keeps the initial import and the import of older activities from flooding the notifications.
            if ($mostRecentStartDateTimeBeforeCalculation?->isBefore($activity->getStartDate())) {
                $activityIdsThatCanHoldNewPersonalRecords[(string) $activityId] = true;
            }

            $distancesForBestEfforts = $activity->getSportType()->getActivityType()->getDistancesForBestEffortCalculation();
            if ((end($distances) - $distances[0]) < $distancesForBestEfforts[0]->toMeter()->toInt()) {
                // Activity is too short for best effort calculation.
                continue;
            }

            $bestEffortsCalculated = false;
            foreach ($distancesForBestEfforts as $distance) {
                if ($activity->getDistance()->toMeter()->toInt() < $distance->toMeter()->toInt()) {
                    // For some reason the Strava distance indicates a longer distance than the actual activity distance.
                    // No clue why this happens, but it does.
                    // Make sure we don't calculate best efforts for distance streams that are longer than the activity distance.
                    continue;
                }
                $n = count($distances);
                $fastestTime = PHP_INT_MAX;
                $startIdx = 0;

                for ($endIdx = 0; $endIdx < $n; ++$endIdx) {
                    while ($startIdx < $endIdx && ($distances[$endIdx] - $distances[$startIdx]) >= $distance->toMeter()->toInt()) {
                        $fastestTime = min($fastestTime, $time[$endIdx] - $time[$startIdx]);
                        ++$startIdx;
                    }
                }

                if (PHP_INT_MAX === $fastestTime) {
                    // No fastest time for this distance.
                    continue;
                }

                $bestEffortsCalculated = true;
                $bestEffort = ActivityBestEffort::create(
                    activityId: $activityId,
                    distanceInMeter: $distance->toMeter(),
                    sportType: $activity->getSportType(),
                    timeInSeconds: $fastestTime,
                );
                $this->activityBestEffortRepository->add($bestEffort);

                $personalRecordKey = $this->buildPersonalRecordKey($bestEffort);
                if (isset($fastestCalculatedBestEfforts[$personalRecordKey]) && $fastestTime >= $fastestCalculatedBestEfforts[$personalRecordKey]->getTimeInSeconds()) {
                    continue;
                }
                $fastestCalculatedBestEfforts[$personalRecordKey] = $bestEffort;
            }

            if ($bestEffortsCalculated) {
                ++$activityWithBestEffortsCalculatedCount;
                $progressIndicator->updateMessage(sprintf(
                    '=> Calculated best efforts for %d activities',
                    $activityWithBestEffortsCalculatedCount
                ));
            }
        }

        $progressIndicator->finish(sprintf(
            '=> Calculated best efforts for %d activities',
            $activityWithBestEffortsCalculatedCount
        ));

        /** @var array<string, ActivityBestEffort> $newPersonalRecords */
        $newPersonalRecords = [];
        foreach ($fastestCalculatedBestEfforts as $personalRecordKey => $bestEffort) {
            if (!isset($activityIdsThatCanHoldNewPersonalRecords[(string) $bestEffort->getActivityId()])) {
                continue;
            }
            if (isset($personalRecordsBeforeCalculation[$personalRecordKey]) && $bestEffort->getTimeInSeconds() >= $personalRecordsBeforeCalculation[$personalRecordKey]->getTimeInSeconds()) {
                continue;
            }
            $newPersonalRecords[$personalRecordKey] = $bestEffort;
        }
        if ([] === $newPersonalRecords) {
            return;
        }

        $output->writeln(sprintf('=> %d new personal record(s)', count($newPersonalRecords)));
        if (!$this->settingsRepository->integrations()->shouldNotifyOnPersonalRecord()) {
            return;
        }

        $this->notifyAboutNewPersonalRecords($newPersonalRecords, $personalRecordsBeforeCalculation);
    }

    /**
     * @param array<string, ActivityBestEffort> $newPersonalRecords
     * @param array<string, ActivityBestEffort> $previousPersonalRecords
     */
    private function notifyAboutNewPersonalRecords(array $newPersonalRecords, array $previousPersonalRecords): void
    {
        uasort($newPersonalRecords, static fn (ActivityBestEffort $a, ActivityBestEffort $b): int => [$a->getSportType()->value, $a->getDistanceInMeter()->toInt()] <=> [$b->getSportType()->value, $b->getDistanceInMeter()->toInt()]);

        $lines = [];
        foreach ($newPersonalRecords as $personalRecordKey => $newPersonalRecord) {
            $distance = $newPersonalRecord->getBestEffortDistance() ?? $newPersonalRecord->getDistanceInMeter();
            $previousPersonalRecord = $previousPersonalRecords[$personalRecordKey] ?? null;
            $lines[] = sprintf(
                '%s %s%s: %s (%s)',
                $newPersonalRecord->getSportType()->transSingular($this->translator, Locale::en_US->value),
                $distance->isLowerThanOne() ? round($distance->toFloat(), 1) : $distance->toInt(),
                $distance->getSymbol(),
                $newPersonalRecord->getFormattedTime(),
                $previousPersonalRecord ? 'previous: '.$previousPersonalRecord->getFormattedTime() : 'first record',
            );
        }

        $activityIds = array_unique(array_map(
            static fn (ActivityBestEffort $personalRecord): string => (string) $personalRecord->getActivityId(),
            $newPersonalRecords
        ));
        $actionPath = 1 === count($activityIds) ? 'activities/'.reset($activityIds) : 'best-efforts';

        $this->commandBus->dispatch(new SendNotification(
            title: 1 === count($newPersonalRecords) ? 'New personal record' : 'New personal records',
            message: implode(PHP_EOL, $lines),
            tags: ['trophy'],
            actionUrl: Url::fromString(rtrim((string) $this->appUrl, '/').'/'.$actionPath),
        ));
    }

    private function buildPersonalRecordKey(ActivityBestEffort $bestEffort): string
    {
        return $bestEffort->getSportType()->value.'_'.$bestEffort->getDistanceInMeter()->toInt();
    }
}

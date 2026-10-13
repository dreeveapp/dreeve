<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;

final readonly class ActivityBestEffortCalculator
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    public function calculateFor(ActivityId $activityId): bool
    {
        $distanceStream = $this->activityStreamRepository->findOneByActivityAndStreamType($activityId, StreamType::DISTANCE);
        $timeStream = $this->activityStreamRepository->findOneByActivityAndStreamType($activityId, StreamType::TIME);

        $activity = $this->activityRepository->find($activityId);
        $distances = $distanceStream->getData();
        $time = $timeStream->getData();

        $distancesForBestEfforts = $activity->getSportType()->getActivityType()->getDistancesForBestEffortCalculation();
        if ((end($distances) - $distances[0]) < $distancesForBestEfforts[0]->toMeter()->toInt()) {
            // Activity is too short for best effort calculation.
            return false;
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
            $this->activityBestEffortRepository->add(
                ActivityBestEffort::create(
                    activityId: $activityId,
                    distanceInMeter: $distance->toMeter(),
                    sportType: $activity->getSportType(),
                    timeInSeconds: $fastestTime,
                )
            );
        }

        return $bestEffortsCalculated;
    }
}

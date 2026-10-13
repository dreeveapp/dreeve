<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteCells;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentEffort\Matching\SegmentEffortMatcher;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class CustomSegmentScanner
{
    private const float MIN_ROUTE_CELL_OVERLAP = 0.5;

    public function __construct(
        private ActivityScanRepository $activityScanRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityRouteSignatureRepository $activityRouteSignatureRepository,
        private SegmentEffortMatcher $segmentEffortMatcher,
    ) {
    }

    public function scan(Segment $segment, RouteCells $segmentCells, ActivityId $activityId): bool
    {
        try {
            $activityCells = array_flip($this->activityRouteSignatureRepository->find($activityId)->getCells()->toArray());
            $cells = $segmentCells->toArray();
            $overlappingCells = array_filter($cells, static fn (int $cell): bool => isset($activityCells[$cell]));
            $isNearSegment = count($overlappingCells) >= count($cells) * self::MIN_ROUTE_CELL_OVERLAP;
        } catch (EntityNotFound) {
            $isNearSegment = true;
        }

        if (!$isNearSegment) {
            $this->activityScanRepository->add(ActivityScan::create(activityId: $activityId, type: ActivityScanType::CUSTOM_SEGMENT, subjectId: (string) $segment->getId()));

            return false;
        }

        $streams = $this->activityStreamRepository->findByActivityId($activityId);
        $matchedEfforts = $this->segmentEffortMatcher->match(
            segment: $segment,
            latLng: $streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? [],
            time: $streams->filterOnType(StreamType::TIME)?->getData() ?? [],
            distance: $streams->filterOnType(StreamType::DISTANCE)?->getData() ?? [],
            watts: $streams->filterOnType(StreamType::WATTS)?->getData() ?? [],
            heartRate: $streams->filterOnType(StreamType::HEART_RATE)?->getData() ?? [],
        );

        if ([] !== $matchedEfforts) {
            $activityStartDate = $this->activityRepository->find($activityId)->getStartDate();
            $time = $streams->filterOnType(StreamType::TIME)?->getData() ?? [];
            foreach ($matchedEfforts as $matchedEffort) {
                $this->segmentEffortRepository->add(SegmentEffort::create(
                    segmentEffortId: SegmentEffortId::random(),
                    segment: $segment,
                    activityId: $activityId,
                    startDateTime: $activityStartDate->modify(sprintf('+%d seconds', (int) $time[$matchedEffort->getStartIndex()])),
                    elapsedTimeInSeconds: $matchedEffort->getElapsedTimeInSeconds(),
                    distance: $segment->getDistance(),
                    averageWatts: $matchedEffort->getAverageWatts(),
                    averageHeartRate: $matchedEffort->getAverageHeartRate(),
                    maxHeartRate: $matchedEffort->getMaxHeartRate(),
                ));
            }
        }

        $this->activityScanRepository->add(ActivityScan::create(
            activityId: $activityId,
            type: ActivityScanType::CUSTOM_SEGMENT,
            subjectId: (string) $segment->getId())
        );

        return true;
    }
}

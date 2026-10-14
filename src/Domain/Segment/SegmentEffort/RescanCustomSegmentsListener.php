<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivitySportTypeWasChanged;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RescanCustomSegmentsListener
{
    public function __construct(
        private SegmentEffortRepository $segmentEffortRepository,
        private ActivityScanRepository $activityScanRepository,
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private SegmentRepository $segmentRepository,
        private CustomSegmentScanner $customSegmentScanner,
    ) {
    }

    #[AsEventListener]
    public function reactToActivitySportTypeWasChanged(ActivitySportTypeWasChanged $event): void
    {
        $activityId = $event->getActivityId();
        $this->segmentEffortRepository->deleteForActivityAndSegmentType($activityId, SegmentType::CUSTOM);
        $this->activityScanRepository->deleteForActivityAndType($activityId, ActivityScanType::CUSTOM_SEGMENT);

        try {
            $latLng = $this->activityStreamRepository->findOneByActivityAndStreamType($activityId, StreamType::LAT_LNG)->getData();
        } catch (EntityNotFound) {
            return;
        }
        if ([] === $latLng) {
            return;
        }

        $activityType = $this->activityRepository->find($activityId)->getSportType()->getActivityType();
        foreach ($this->segmentRepository->findByType(SegmentType::CUSTOM) as $segment) {
            if ($segment->getSportType()->getActivityType() !== $activityType) {
                continue;
            }
            if (!($polyline = $segment->getPolyline()) instanceof EncodedPolyline) {
                continue;
            }

            $this->customSegmentScanner->scan(
                segment: $segment,
                segmentCells: RouteGrid::cellsFor($polyline),
                activityId: $activityId
            );
        }
    }
}

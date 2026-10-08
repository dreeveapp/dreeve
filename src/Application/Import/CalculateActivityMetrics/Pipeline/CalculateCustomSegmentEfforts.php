<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentEffort\Matching\SegmentEffortMatcher;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 3)]
final readonly class CalculateCustomSegmentEfforts implements CalculateActivityMetricsStep
{
    private const float MIN_ROUTE_CELL_OVERLAP = 0.5;

    public function __construct(
        private SegmentRepository $segmentRepository,
        private ActivityScanRepository $activityScanRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityRouteSignatureRepository $activityRouteSignatureRepository,
        private RouteGrid $routeGrid,
        private SegmentEffortMatcher $segmentEffortMatcher,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Scanned 0 activities for custom segment efforts');

        $scannedActivityCount = 0;
        foreach ($this->segmentRepository->findByType(SegmentType::CUSTOM) as $segment) {
            if (!($polyline = $segment->getPolyline()) instanceof EncodedPolyline) {
                continue;
            }
            $segmentCells = $this->routeGrid->cellsFor($polyline)->toArray();

            foreach ($this->activityScanRepository->findActivityIdsThatNeedScanning(
                type: ActivityScanType::CUSTOM_SEGMENT,
                subjectId: (string) $segment->getId(),
                sportTypes: $segment->getSportType()->getActivityType()->getSportTypes(),
                requiredStreamTypes: [StreamType::LAT_LNG],
            ) as $activityId) {
                try {
                    $activityCells = array_flip($this->activityRouteSignatureRepository->find($activityId)->getCells()->toArray());
                    $overlappingCells = array_filter($segmentCells, static fn (int $cell): bool => isset($activityCells[$cell]));
                    $isNearSegment = count($overlappingCells) >= count($segmentCells) * self::MIN_ROUTE_CELL_OVERLAP;
                } catch (EntityNotFound) {
                    $isNearSegment = true;
                }

                if (!$isNearSegment) {
                    $this->activityScanRepository->add(ActivityScan::create(activityId: $activityId, type: ActivityScanType::CUSTOM_SEGMENT, subjectId: (string) $segment->getId()));
                    continue;
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

                $this->activityScanRepository->add(ActivityScan::create(activityId: $activityId, type: ActivityScanType::CUSTOM_SEGMENT, subjectId: (string) $segment->getId()));

                ++$scannedActivityCount;
                $progressIndicator->updateMessage(sprintf(
                    '=> Scanned %d activities for custom segment efforts',
                    $scannedActivityCount
                ));
            }
        }

        $progressIndicator->finish(sprintf(
            '=> Scanned %d activities for custom segment efforts',
            $scannedActivityCount
        ));
    }
}

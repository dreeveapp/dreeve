<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentActivityScan\SegmentActivityScan;
use App\Domain\Segment\SegmentActivityScan\SegmentActivityScanRepository;
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
        private SegmentActivityScanRepository $segmentActivityScanRepository,
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

            foreach ($this->segmentActivityScanRepository->findActivityIdsThatNeedScanning($segment) as $activityId) {
                try {
                    $activityCells = array_flip($this->activityRouteSignatureRepository->find($activityId)->getCells()->toArray());
                    $overlappingCells = array_filter($segmentCells, static fn (int $cell): bool => isset($activityCells[$cell]));
                    $isNearSegment = count($overlappingCells) >= count($segmentCells) * self::MIN_ROUTE_CELL_OVERLAP;
                } catch (EntityNotFound) {
                    $isNearSegment = true;
                }

                if (!$isNearSegment) {
                    $this->segmentActivityScanRepository->add(SegmentActivityScan::create($segment->getId(), $activityId));
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
                            segmentId: $segment->getId(),
                            activityId: $activityId,
                            startDateTime: $activityStartDate->modify(sprintf('+%d seconds', (int) $time[$matchedEffort->getStartIndex()])),
                            name: (string) $segment->getOriginalName(),
                            elapsedTimeInSeconds: $matchedEffort->getElapsedTimeInSeconds(),
                            distance: $segment->getDistance(),
                            averageWatts: $matchedEffort->getAverageWatts(),
                            averageHeartRate: $matchedEffort->getAverageHeartRate(),
                            maxHeartRate: $matchedEffort->getMaxHeartRate(),
                        ));
                    }
                }

                $this->segmentActivityScanRepository->add(SegmentActivityScan::create($segment->getId(), $activityId));

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

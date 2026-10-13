<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Segment\SegmentEffort\CustomSegmentScanner;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 3)]
final readonly class CalculateCustomSegmentEfforts implements CalculateActivityMetricsStep
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private ActivityScanRepository $activityScanRepository,
        private RouteGrid $routeGrid,
        private CustomSegmentScanner $customSegmentScanner,
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
            $segmentCells = $this->routeGrid->cellsFor($polyline);

            foreach ($this->activityScanRepository->findActivityIdsThatNeedScanning(
                type: ActivityScanType::CUSTOM_SEGMENT,
                subjectId: (string) $segment->getId(),
                sportTypes: $segment->getSportType()->getActivityType()->getSportTypes(),
                requiredStreamTypes: [StreamType::LAT_LNG],
            ) as $activityId) {
                if (!$this->customSegmentScanner->scan(
                    segment: $segment,
                    segmentCells: $segmentCells,
                    activityId: $activityId)
                ) {
                    continue;
                }

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

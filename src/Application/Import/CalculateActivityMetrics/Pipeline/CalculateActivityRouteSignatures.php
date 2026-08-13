<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Signature\ActivityRouteSignature;
use App\Domain\Activity\Route\Signature\ActivityRouteSignatureRepository;
use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Infrastructure\Console\ProgressIndicator;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 5)]
final readonly class CalculateActivityRouteSignatures implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityRouteSignatureRepository $activityRouteSignatureRepository,
        private RouteGrid $routeGrid,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated route signatures for 0 activities');

        $activityIds = $this->activityRouteSignatureRepository->findActivityIdsThatNeedRouteSignatureCalculation();

        $activityWithRouteSignatureCalculatedCount = 0;
        foreach ($activityIds as $activityId) {
            $polyline = $this->activityRepository->find($activityId)->getEncodedPolyline();
            if (!$polyline instanceof EncodedPolyline) {
                continue;
            }

            $this->activityRouteSignatureRepository->deleteForActivity($activityId);
            $this->activityRouteSignatureRepository->add(ActivityRouteSignature::create(
                activityId: $activityId,
                polylineChecksum: $this->routeGrid->checksumFor($polyline),
                cells: $this->routeGrid->cellsFor($polyline),
                waypoints: $this->routeGrid->waypointsFor($polyline),
            ));

            ++$activityWithRouteSignatureCalculatedCount;
            $progressIndicator->updateMessage(sprintf(
                '=> Calculated route signatures for %d activities',
                $activityWithRouteSignatureCalculatedCount
            ));
        }

        $progressIndicator->finish(sprintf(
            '=> Calculated route signatures for %d activities',
            $activityWithRouteSignatureCalculatedCount
        ));
    }
}

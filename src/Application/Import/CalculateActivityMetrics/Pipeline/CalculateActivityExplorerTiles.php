<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics\Pipeline;

use App\Domain\Activity\Route\ExplorerTile\ExplorerTileScanner;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Console\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 2)]
final readonly class CalculateActivityExplorerTiles implements CalculateActivityMetricsStep
{
    public function __construct(
        private ActivityScanRepository $activityScanRepository,
        private ExplorerTileScanner $explorerTileScanner,
    ) {
    }

    public function process(OutputInterface $output): void
    {
        $progressIndicator = new ProgressIndicator($output);
        $progressIndicator->start('=> Calculated explorer tiles for 0 activities');

        $scannedActivityCount = 0;
        foreach ($this->activityScanRepository->findActivityIdsThatNeedScanning(
            type: ActivityScanType::EXPLORER_TILES,
            subjectId: '',
            sportTypes: SportTypes::fromArray(array_values(array_filter(
                SportType::cases(),
                fn (SportType $sportType): bool => $sportType->supportsReverseGeocoding()
            ))),
            requiredStreamTypes: [],
        ) as $activityId) {
            if (!$this->explorerTileScanner->scan($activityId)) {
                continue;
            }

            ++$scannedActivityCount;
            $progressIndicator->updateMessage(sprintf(
                '=> Calculated explorer tiles for %d activities',
                $scannedActivityCount
            ));
        }

        $progressIndicator->finish(sprintf(
            '=> Calculated explorer tiles for %d activities',
            $scannedActivityCount
        ));
    }
}

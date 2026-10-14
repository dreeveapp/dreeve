<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Scan\ActivityScan;
use App\Domain\Activity\Scan\ActivityScanRepository;
use App\Domain\Activity\Scan\ActivityScanType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class ExplorerTileScanner
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityScanRepository $activityScanRepository,
        private ActivityExplorerTilesRepository $activityExplorerTilesRepository,
    ) {
    }

    public function scan(ActivityId $activityId): bool
    {
        if (!$this->activityScanRepository->isScanned(activityId: $activityId, type: ActivityScanType::STREAMS)) {
            return false;
        }

        $activity = $this->activityRepository->find($activityId);
        if (WorldType::REAL_WORLD === $activity->getWorldType()) {
            try {
                $latLng = $this->activityStreamRepository->findOneByActivityAndStreamType(
                    activityId: $activityId,
                    streamType: StreamType::LAT_LNG,
                )->getData();
            } catch (EntityNotFound) {
                $latLng = [];
            }

            $tiles = ExplorerTileGrid::tilesFor(
                [] !== $latLng ? $latLng : ($activity->getEncodedPolyline()?->decodeAndPairLatLng() ?? [])
            );
            if (!$tiles->isEmpty()) {
                $this->activityExplorerTilesRepository->add(ActivityExplorerTiles::create(
                    activityId: $activityId,
                    tiles: $tiles,
                ));
            }
        }

        $this->activityScanRepository->add(ActivityScan::create(
            activityId: $activityId,
            type: ActivityScanType::EXPLORER_TILES,
        ));

        return true;
    }
}

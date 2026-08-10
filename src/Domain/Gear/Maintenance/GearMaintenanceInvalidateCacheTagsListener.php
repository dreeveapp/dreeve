<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogWasUpdated;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class GearMaintenanceInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToGearMaintenanceConfigWasUpdated(GearMaintenanceConfigWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::GEAR_MAINTENANCE);
    }

    #[AsEventListener]
    public function reactToGearMaintenanceLogWasUpdated(GearMaintenanceLogWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::GEAR_MAINTENANCE);
    }
}

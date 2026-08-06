<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\RenderCache;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class GearInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToGearWasAdded(GearWasAdded $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::GEAR);
    }

    #[AsEventListener]
    public function reactToGearWasUpdated(GearWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::GEAR);
    }
}

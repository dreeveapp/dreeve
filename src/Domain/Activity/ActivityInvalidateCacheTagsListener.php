<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\RenderCache;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ActivityInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToActivityImagesHaveBeenUpdated(ActivityImagesHaveBeenUpdated $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::ACTIVITY_IMAGES);
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::ACTIVITY_IMAGES);
    }
}

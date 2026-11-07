<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ActivityCollectionInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToActivityCollectionWasAdded(ActivityCollectionWasAdded $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::ACTIVITY_COLLECTIONS);
    }

    #[AsEventListener]
    public function reactToActivityCollectionWasUpdated(ActivityCollectionWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(
            RootCacheTag::ACTIVITY_COLLECTIONS,
            ActivityCollectionCacheTag::for($event->getActivityCollectionId()),
        );
    }

    #[AsEventListener]
    public function reactToActivityCollectionWasDeleted(ActivityCollectionWasDeleted $event): void
    {
        $this->renderCache->invalidateTags(
            RootCacheTag::ACTIVITY_COLLECTIONS,
            ActivityCollectionCacheTag::for($event->getActivityCollectionId()),
        );
    }
}

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
    public function reactToActivityWasAdded(ActivityWasAdded $event): void
    {
        $this->renderCache->invalidateTags(
            CacheTag::ACTIVITIES,
            CacheTag::ACTIVITIES->forYear($event->getYear()),
        );
    }

    #[AsEventListener]
    public function reactToActivityWasUpdated(ActivityWasUpdated $event): void
    {
        $cacheTags = [CacheTag::ACTIVITIES, ActivityCacheTag::for($event->getActivityId())];
        foreach ($event->getYears() as $year) {
            $cacheTags[] = CacheTag::ACTIVITIES->forYear($year);
        }

        $this->renderCache->invalidateTags(...$cacheTags);
    }

    #[AsEventListener]
    public function reactToActivityImagesHaveBeenUpdated(ActivityImagesHaveBeenUpdated $event): void
    {
        $this->renderCache->invalidateTags(
            CacheTag::ACTIVITY_IMAGES,
            CacheTag::ACTIVITY_IMAGES->forYear($event->getYear()),
        );
    }

    #[AsEventListener]
    public function reactToActivityRouteWasUpdated(ActivityRouteWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::ACTIVITY_ROUTE);
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $this->renderCache->invalidateTags(
            CacheTag::ACTIVITIES,
            CacheTag::ACTIVITIES->forYear($event->getYear()),
            ActivityCacheTag::for($event->getActivityId()),
            CacheTag::ACTIVITY_IMAGES,
            CacheTag::ACTIVITY_IMAGES->forYear($event->getYear()),
            CacheTag::ACTIVITY_ROUTE,
        );
    }
}

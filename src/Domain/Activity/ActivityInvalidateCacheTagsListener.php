<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\RootCacheTag;
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
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITIES->forYear($event->getYear()),
            RootCacheTag::ACTIVITIES->forMonth($event->getMonth()),
        );
    }

    #[AsEventListener]
    public function reactToActivityWasUpdated(ActivityWasUpdated $event): void
    {
        $cacheTags = [RootCacheTag::ACTIVITIES, ActivityCacheTag::for($event->getActivityId())];
        foreach ($event->getYears() as $year) {
            $cacheTags[] = RootCacheTag::ACTIVITIES->forYear($year);
        }
        foreach ($event->getMonths() as $month) {
            $cacheTags[] = RootCacheTag::ACTIVITIES->forMonth($month);
        }

        $this->renderCache->invalidateTags(...$cacheTags);
    }

    #[AsEventListener]
    public function reactToActivityImagesHaveBeenUpdated(ActivityImagesHaveBeenUpdated $event): void
    {
        $this->renderCache->invalidateTags(
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::ACTIVITY_IMAGES->forYear($event->getYear()),
        );
    }

    #[AsEventListener]
    public function reactToActivityRouteWasUpdated(ActivityRouteWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::ACTIVITY_ROUTE);
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $this->renderCache->invalidateTags(
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITIES->forYear($event->getYear()),
            RootCacheTag::ACTIVITIES->forMonth($event->getMonth()),
            ActivityCacheTag::for($event->getActivityId()),
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::ACTIVITY_IMAGES->forYear($event->getYear()),
            RootCacheTag::ACTIVITY_ROUTE,
        );
    }
}

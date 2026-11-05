<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityCollectionWithActivities>
 */
final class ActivityCollectionsWithActivities extends Collection
{
    public static function create(
        ActivityCollections $activityCollections,
        Activities $activities,
    ): self {
        $activityCollectionsWithActivities = self::empty();
        foreach ($activityCollections as $activityCollection) {
            $members = $activities->filter(
                static fn (Activity $activity): bool => $activityCollection->getActivityIds()->has($activity->getId()),
            );
            if ($members->isEmpty()) {
                continue;
            }

            $activityCollectionsWithActivities->add(ActivityCollectionWithActivities::create(
                activityCollection: $activityCollection,
                activities: $members,
            ));
        }

        return $activityCollectionsWithActivities->usort(
            static fn (ActivityCollectionWithActivities $a, ActivityCollectionWithActivities $b): int => $b->getStartDate() <=> $a->getStartDate(),
        );
    }

    public function getItemClassName(): string
    {
        return ActivityCollectionWithActivities::class;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<ActivityCollectionSummary>
 */
final class ActivityCollectionSummaries extends Collection
{
    public static function create(
        ActivityCollections $activityCollections,
        Activities $activities,
    ): self {
        $summaries = self::empty();
        foreach ($activityCollections as $activityCollection) {
            $members = $activities->filter(
                static fn (Activity $activity): bool => $activityCollection->getActivityIds()->has($activity->getId()),
            );
            if ($members->isEmpty()) {
                continue;
            }

            $summaries->add(ActivityCollectionSummary::create(
                activityCollection: $activityCollection,
                activities: $members,
            ));
        }

        return $summaries;
    }

    /**
     * @return array<int, list<ActivityCollectionSummary>>
     */
    public function groupByYear(): array
    {
        $summariesPerYear = [];
        foreach ($this->usort(static fn (ActivityCollectionSummary $a, ActivityCollectionSummary $b): int => $b->getStartDate() <=> $a->getStartDate()) as $summary) {
            $summariesPerYear[$summary->getStartDate()->getYear()][] = $summary;
        }

        return $summariesPerYear;
    }

    public function getItemClassName(): string
    {
        return ActivityCollectionSummary::class;
    }
}

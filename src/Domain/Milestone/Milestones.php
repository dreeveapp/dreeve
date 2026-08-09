<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<Milestone>
 */
final class Milestones extends Collection
{
    public function getActivityIds(): ActivityIds
    {
        return ActivityIds::fromArray(array_filter($this->map(
            fn (Milestone $milestone): ?ActivityId => $milestone->getActivityId()
        )))->unique();
    }

    public function getItemClassName(): string
    {
        return Milestone::class;
    }

    /**
     * @return MilestoneFilterGroup[]
     */
    public function getUniqueFilterGroups(): array
    {
        $groups = [];
        /** @var Milestone $milestone */
        foreach ($this as $milestone) {
            $group = $milestone->getCategory()->getFilterGroup();
            $groups[array_search($group, MilestoneFilterGroup::cases())] = $group;
        }

        ksort($groups);

        return $groups;
    }
}

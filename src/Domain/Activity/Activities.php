<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;

/**
 * @extends Collection<\App\Domain\Activity\Activity>
 */
final class Activities extends Collection
{
    public function getItemClassName(): string
    {
        return Activity::class;
    }

    /**
     * @return array<string, Activity>
     */
    public function keyByActivityId(): array
    {
        $activities = [];
        foreach ($this as $activity) {
            $activities[(string) $activity->getId()] = $activity;
        }

        return $activities;
    }

    /**
     * @return array<string, Activities>
     */
    public function groupByActivityType(ActivityTypes $activityTypes): array
    {
        $activitiesPerActivityType = [];
        foreach ($activityTypes as $activityType) {
            $activitiesPerActivityType[$activityType->value] = Activities::empty();
        }

        foreach ($this as $activity) {
            $activityType = $activity->getSportType()->getActivityType()->value;
            $activitiesPerActivityType[$activityType] ??= Activities::empty();
            $activitiesPerActivityType[$activityType]->add($activity);
        }

        return $activitiesPerActivityType;
    }

    public function getFirstActivityStartDate(): SerializableDateTime
    {
        $startDate = null;
        foreach ($this as $activity) {
            if ($startDate instanceof SerializableDateTime && $activity->getStartDate()->isAfterOrOn($startDate)) {
                continue;
            }
            $startDate = $activity->getStartDate();
        }

        if (!$startDate instanceof SerializableDateTime) {
            throw new \RuntimeException('No activities found');
        }

        return $startDate;
    }

    public function getUniqueYears(): Years
    {
        $years = Years::empty();
        foreach ($this as $activity) {
            $activityYear = Year::fromInt($activity->getStartDate()->getYear());
            if ($years->has($activityYear)) {
                continue;
            }
            $years->add($activityYear);
        }

        return $years;
    }
}

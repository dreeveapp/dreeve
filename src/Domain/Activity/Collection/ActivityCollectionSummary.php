<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class ActivityCollectionSummary
{
    private function __construct(
        private ActivityCollection $activityCollection,
        private Activities $activities,
    ) {
    }

    public static function create(
        ActivityCollection $activityCollection,
        Activities $activities,
    ): self {
        if ($activities->isEmpty()) {
            throw new \InvalidArgumentException('An activity collection summary needs at least one activity.');
        }

        return new self(
            activityCollection: $activityCollection,
            activities: $activities->usort(static fn (Activity $a, Activity $b): int => $a->getStartDate() <=> $b->getStartDate()),
        );
    }

    public function getActivityCollection(): ActivityCollection
    {
        return $this->activityCollection;
    }

    public function getActivities(): Activities
    {
        return $this->activities;
    }

    public function getStartDate(): SerializableDateTime
    {
        $activity = $this->activities->getFirst();
        assert($activity instanceof Activity);

        return $activity->getStartDate();
    }

    public function getEndDate(): SerializableDateTime
    {
        $activity = $this->activities->getLast();
        assert($activity instanceof Activity);

        return $activity->getStartDate();
    }

    public function getNumberOfDays(): int
    {
        return (int) $this->getStartDate()->setTime(0, 0)->diff($this->getEndDate()->setTime(0, 0))->days + 1;
    }

    public function getDistance(): Kilometer
    {
        return Kilometer::from($this->activities->sum(static fn (Activity $activity): float => $activity->getDistance()->toFloat()));
    }

    public function getElevation(): Meter
    {
        return Meter::from($this->activities->sum(static fn (Activity $activity): float => $activity->getElevation()->toFloat()));
    }

    /**
     * @return string[]
     */
    public function getCountryCodes(): array
    {
        return array_values(array_unique(array_merge(...$this->activities->map(
            static fn (Activity $activity): array => $activity->getRouteGeography()->getPassedThroughCountries(),
        ))));
    }
}

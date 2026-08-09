<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Calendar\Month;
use App\Infrastructure\Eventing\DomainEvent;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;

final class ActivityWasDeleted extends DomainEvent
{
    public function __construct(
        private readonly ActivityId $activityId,
        private readonly SerializableDateTime $startDate,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getYear(): Year
    {
        return Year::fromDate($this->startDate);
    }

    public function getMonth(): Month
    {
        return Month::fromDate($this->startDate);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Eventing\DomainEvent;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;

final class ActivityWasUpdated extends DomainEvent
{
    public function __construct(
        private readonly ActivityId $activityId,
        private readonly SerializableDateTime $startDate,
        private readonly SerializableDateTime $previousStartDate,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    /**
     * @return Year[]
     */
    public function getYears(): array
    {
        $years = [Year::fromDate($this->startDate)];
        if ($this->startDate->getYear() !== $this->previousStartDate->getYear()) {
            $years[] = Year::fromDate($this->previousStartDate);
        }

        return $years;
    }
}

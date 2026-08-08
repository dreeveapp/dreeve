<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Eventing\DomainEvent;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;

final class ActivityWasAdded extends DomainEvent
{
    public function __construct(
        private readonly SerializableDateTime $startDate,
    ) {
    }

    public function getYear(): Year
    {
        return Year::fromDate($this->startDate);
    }
}

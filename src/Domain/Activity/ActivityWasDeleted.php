<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Eventing\DomainEvent;

final class ActivityWasDeleted extends DomainEvent
{
    public function __construct(
        private readonly ActivityId $activityId,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }
}

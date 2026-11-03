<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Infrastructure\Eventing\DomainEvent;

final class ActivityCollectionWasUpdated extends DomainEvent
{
    public function __construct(
        private readonly ActivityCollectionId $activityCollectionId,
    ) {
    }

    public function getActivityCollectionId(): ActivityCollectionId
    {
        return $this->activityCollectionId;
    }
}

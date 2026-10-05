<?php

declare(strict_types=1);

namespace App\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Image\ActivityImageId;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class RemoveActivityImage extends DomainCommand
{
    public function __construct(
        private ActivityId $activityId,
        private ActivityImageId $activityImageId,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getActivityImageId(): ActivityImageId
    {
        return $this->activityImageId;
    }
}

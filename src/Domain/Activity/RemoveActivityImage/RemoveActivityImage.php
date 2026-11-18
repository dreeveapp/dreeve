<?php

declare(strict_types=1);

namespace App\Domain\Activity\RemoveActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Media\MediaItemId;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class RemoveActivityImage extends DomainCommand
{
    public function __construct(
        private ActivityId $activityId,
        private MediaItemId $mediaItemId,
    ) {
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getMediaItemId(): MediaItemId
    {
        return $this->mediaItemId;
    }
}

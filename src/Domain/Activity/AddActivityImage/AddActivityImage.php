<?php

declare(strict_types=1);

namespace App\Domain\Activity\AddActivityImage;

use App\Domain\Activity\ActivityId;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class AddActivityImage extends DomainCommand
{
    public function __construct(
        private ActivityId $activityId,
        private MediaItemId $mediaItemId,
        private NewMediaItem $newMediaItem,
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

    public function getNewMediaItem(): NewMediaItem
    {
        return $this->newMediaItem;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\Eventing\DomainEvent;

final class MediaItemWasUpdated extends DomainEvent
{
    public function __construct(
        private readonly MediaItemId $mediaItemId,
    ) {
    }

    public function getMediaItemId(): MediaItemId
    {
        return $this->mediaItemId;
    }
}

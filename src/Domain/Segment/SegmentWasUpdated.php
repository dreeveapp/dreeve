<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\Eventing\DomainEvent;

final class SegmentWasUpdated extends DomainEvent
{
    public function __construct(
        private readonly SegmentId $segmentId,
    ) {
    }

    public function getSegmentId(): SegmentId
    {
        return $this->segmentId;
    }
}

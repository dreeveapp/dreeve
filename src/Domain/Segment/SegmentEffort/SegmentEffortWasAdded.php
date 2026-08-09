<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Segment\SegmentId;
use App\Infrastructure\Eventing\DomainEvent;

final class SegmentEffortWasAdded extends DomainEvent
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

<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Segment\SegmentIds;
use App\Infrastructure\Eventing\DomainEvent;

final class SegmentEffortsWereDeleted extends DomainEvent
{
    public function __construct(
        private readonly SegmentIds $segmentIds,
    ) {
    }

    public function getSegmentIds(): SegmentIds
    {
        return $this->segmentIds;
    }
}

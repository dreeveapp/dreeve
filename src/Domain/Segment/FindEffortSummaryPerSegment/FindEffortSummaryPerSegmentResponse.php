<?php

declare(strict_types=1);

namespace App\Domain\Segment\FindEffortSummaryPerSegment;

use App\Domain\Segment\SegmentId;
use App\Infrastructure\CQRS\Query\Response;

final readonly class FindEffortSummaryPerSegmentResponse implements Response
{
    public function __construct(
        /** @var array<string, SegmentEffortSummary> */
        private array $summaryPerSegmentId,
    ) {
    }

    public function getForSegment(SegmentId $segmentId): ?SegmentEffortSummary
    {
        return $this->summaryPerSegmentId[(string) $segmentId] ?? null;
    }
}

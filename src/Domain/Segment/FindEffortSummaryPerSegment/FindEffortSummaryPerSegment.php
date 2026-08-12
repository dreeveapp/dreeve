<?php

declare(strict_types=1);

namespace App\Domain\Segment\FindEffortSummaryPerSegment;

use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Segment\FindEffortSummaryPerSegment\FindEffortSummaryPerSegmentResponse>
 */
final readonly class FindEffortSummaryPerSegment implements Query
{
}

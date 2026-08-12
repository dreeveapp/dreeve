<?php

declare(strict_types=1);

namespace App\Domain\Segment\FindEffortSummaryPerSegment;

use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class SegmentEffortSummary
{
    private function __construct(
        private int $numberOfTimesRidden,
        private SegmentEffort $bestEffort,
        private SerializableDateTime $lastEffortDate,
    ) {
    }

    public static function create(
        int $numberOfTimesRidden,
        SegmentEffort $bestEffort,
        SerializableDateTime $lastEffortDate,
    ): self {
        return new self(
            numberOfTimesRidden: $numberOfTimesRidden,
            bestEffort: $bestEffort,
            lastEffortDate: $lastEffortDate,
        );
    }

    public function getNumberOfTimesRidden(): int
    {
        return $this->numberOfTimesRidden;
    }

    public function getBestEffort(): SegmentEffort
    {
        return $this->bestEffort;
    }

    public function getLastEffortDate(): SerializableDateTime
    {
        return $this->lastEffortDate;
    }
}

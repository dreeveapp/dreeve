<?php

declare(strict_types=1);

namespace App\Domain\Segment\Search;

use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Infrastructure\Repository\Item;

final readonly class SegmentSearchResult implements Item
{
    private function __construct(
        private Segment $segment,
        private int $numberOfTimesRidden,
        private ?SegmentEffort $bestEffort,
    ) {
    }

    public static function fromState(
        Segment $segment,
        int $numberOfTimesRidden,
        ?SegmentEffort $bestEffort,
    ): self {
        return new self(
            segment: $segment,
            numberOfTimesRidden: $numberOfTimesRidden,
            bestEffort: $bestEffort,
        );
    }

    public function getSegment(): Segment
    {
        return $this->segment;
    }

    public function getNumberOfTimesRidden(): int
    {
        return $this->numberOfTimesRidden;
    }

    public function getBestEffort(): ?SegmentEffort
    {
        return $this->bestEffort;
    }
}

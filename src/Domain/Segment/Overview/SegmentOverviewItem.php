<?php

declare(strict_types=1);

namespace App\Domain\Segment\Overview;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Repository\Item;
use App\Infrastructure\ValueObject\String\Name;

final readonly class SegmentOverviewItem implements Item
{
    private function __construct(
        private SegmentId $segmentId,
        private Name $name,
        private SegmentType $type,
        private SportType $sportType,
        private Kilometer $distance,
        private ?float $averageGradient,
        private int $numberOfEfforts,
    ) {
    }

    public static function fromState(
        SegmentId $segmentId,
        Name $name,
        SegmentType $type,
        SportType $sportType,
        Kilometer $distance,
        ?float $averageGradient,
        int $numberOfEfforts,
    ): self {
        return new self(
            segmentId: $segmentId,
            name: $name,
            type: $type,
            sportType: $sportType,
            distance: $distance,
            averageGradient: $averageGradient,
            numberOfEfforts: $numberOfEfforts,
        );
    }

    public function getSegmentId(): SegmentId
    {
        return $this->segmentId;
    }

    public function getName(): Name
    {
        return $this->name;
    }

    public function getType(): SegmentType
    {
        return $this->type;
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    public function getAverageGradient(): ?float
    {
        return $this->averageGradient;
    }

    public function getNumberOfEfforts(): int
    {
        return $this->numberOfEfforts;
    }
}

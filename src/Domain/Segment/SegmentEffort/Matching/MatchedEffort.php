<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort\Matching;

final readonly class MatchedEffort
{
    public function __construct(
        private int $startIndex,
        private float $elapsedTimeInSeconds,
        private ?float $averageWatts,
        private ?int $averageHeartRate,
        private ?int $maxHeartRate,
    ) {
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getElapsedTimeInSeconds(): float
    {
        return $this->elapsedTimeInSeconds;
    }

    public function getAverageWatts(): ?float
    {
        return $this->averageWatts;
    }

    public function getAverageHeartRate(): ?int
    {
        return $this->averageHeartRate;
    }

    public function getMaxHeartRate(): ?int
    {
        return $this->maxHeartRate;
    }
}

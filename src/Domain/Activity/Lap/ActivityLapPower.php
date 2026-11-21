<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

final readonly class ActivityLapPower
{
    private function __construct(
        private ActivityLapId $lapId,
        private ?int $averageWatts,
        private ?int $maxWatts,
    ) {
    }

    public static function create(
        ActivityLapId $lapId,
        ?int $averageWatts,
        ?int $maxWatts,
    ): self {
        return new self(
            lapId: $lapId,
            averageWatts: $averageWatts,
            maxWatts: $maxWatts,
        );
    }

    public function getLapId(): ActivityLapId
    {
        return $this->lapId;
    }

    public function getAverageWatts(): ?int
    {
        return $this->averageWatts;
    }

    public function getMaxWatts(): ?int
    {
        return $this->maxWatts;
    }

    public function hasPower(): bool
    {
        return null !== $this->averageWatts;
    }
}

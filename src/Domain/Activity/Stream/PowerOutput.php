<?php

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;

final readonly class PowerOutput
{
    private function __construct(
        private int $timeIntervalInSeconds,
        private string $formattedTimeInterval,
        private int $power,
        private ?float $relativePower,
        private ?ActivityId $activityId = null,
    ) {
    }

    public static function fromState(
        int $timeIntervalInSeconds,
        string $formattedTimeInterval,
        int $power,
        ?float $relativePower,
        ?ActivityId $activityId = null,
    ): self {
        return new self(
            timeIntervalInSeconds: $timeIntervalInSeconds,
            formattedTimeInterval: $formattedTimeInterval,
            power: $power,
            relativePower: $relativePower,
            activityId: $activityId
        );
    }

    public function getTimeIntervalInSeconds(): int
    {
        return $this->timeIntervalInSeconds;
    }

    public function getFormattedTimeInterval(): string
    {
        return $this->formattedTimeInterval;
    }

    public function getPower(): int
    {
        return $this->power;
    }

    public function getRelativePower(): ?float
    {
        return $this->relativePower;
    }

    public function getActivityId(): ?ActivityId
    {
        return $this->activityId;
    }
}

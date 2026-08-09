<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\PowerOutput;
use App\Domain\Activity\Stream\PowerOutputs;

final readonly class EnrichedActivity
{
    private function __construct(
        private Activity $activity,
        private ?int $normalizedPower,
        private ?int $maxCadence,
        private ?string $gearName,
        private PowerOutputs $bestPowerOutputs,
    ) {
    }

    public static function fromState(
        Activity $activity,
        ?int $normalizedPower,
        ?int $maxCadence,
        ?string $gearName,
        PowerOutputs $bestPowerOutputs,
    ): self {
        return new self(
            activity: $activity,
            normalizedPower: $normalizedPower,
            maxCadence: $maxCadence,
            gearName: $gearName,
            bestPowerOutputs: $bestPowerOutputs,
        );
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getNormalizedPower(): ?int
    {
        return $this->normalizedPower;
    }

    public function getMaxCadence(): ?int
    {
        return $this->maxCadence;
    }

    public function getGearName(): ?string
    {
        return $this->gearName;
    }

    public function hasDetailedPowerData(): bool
    {
        return !$this->bestPowerOutputs->isEmpty();
    }

    public function getBestAveragePowerForTimeInterval(int $timeInterval): ?PowerOutput
    {
        return $this->bestPowerOutputs->find(
            fn (PowerOutput $bestPowerOutput): bool => $bestPowerOutput->getTimeIntervalInSeconds() === $timeInterval
        );
    }
}

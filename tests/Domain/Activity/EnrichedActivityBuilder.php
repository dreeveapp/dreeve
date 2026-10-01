<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\EnrichedActivity;
use App\Domain\Activity\Stream\PowerOutputs;

final class EnrichedActivityBuilder
{
    private Activity $activity;
    private ?int $normalizedPower = null;
    private ?int $maxCadence = null;
    private ?string $gearName = null;
    private PowerOutputs $bestPowerOutputs;

    private function __construct()
    {
        $this->activity = ActivityBuilder::fromDefaults()->build();
        $this->bestPowerOutputs = PowerOutputs::empty();
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): EnrichedActivity
    {
        return EnrichedActivity::fromState(
            activity: $this->activity,
            normalizedPower: $this->normalizedPower,
            maxCadence: $this->maxCadence,
            gearName: $this->gearName,
            bestPowerOutputs: $this->bestPowerOutputs,
        );
    }

    public function withActivity(Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function withNormalizedPower(?int $normalizedPower): self
    {
        $this->normalizedPower = $normalizedPower;

        return $this;
    }

    public function withMaxCadence(?int $maxCadence): self
    {
        $this->maxCadence = $maxCadence;

        return $this;
    }

    public function withGearName(?string $gearName): self
    {
        $this->gearName = $gearName;

        return $this;
    }

    public function withBestPowerOutputs(PowerOutputs $bestPowerOutputs): self
    {
        $this->bestPowerOutputs = $bestPowerOutputs;

        return $this;
    }
}

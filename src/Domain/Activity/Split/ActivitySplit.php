<?php

declare(strict_types=1);

namespace App\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'ActivitySplit_activityIdUnitSystemIndex', columns: ['activityId', 'unitSystem'])]
final readonly class ActivitySplit implements SupportsAITooling
{
    use ProvideTimeFormats;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Id, ORM\Column(type: 'string')]
        private UnitSystem $unitSystem,
        #[ORM\Id, ORM\Column(type: 'integer')]
        private int $splitNumber,
        #[ORM\Column(type: 'integer')]
        private Meter $distance,
        #[ORM\Column(type: 'integer')]
        private int $elapsedTimeInSeconds,
        #[ORM\Column(type: 'integer')]
        private int $movingTimeInSeconds,
        #[ORM\Column(type: 'integer')]
        private Meter $elevationDifference,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $averageSpeed,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $minAverageSpeed,
        #[ORM\Column(type: 'integer')]
        private MetersPerSecond $maxAverageSpeed,
        #[ORM\Column(type: 'integer')]
        private int $paceZone,
        #[ORM\Column(type: 'float', nullable: true)]
        private ?SecPerKm $gapPaceInSecondsPerKm,
    ) {
    }

    public static function create(
        ActivityId $activityId,
        UnitSystem $unitSystem,
        int $splitNumber,
        Meter $distance,
        int $elapsedTimeInSeconds,
        int $movingTimeInSeconds,
        Meter $elevationDifference,
        MetersPerSecond $averageSpeed,
        MetersPerSecond $minAverageSpeed,
        MetersPerSecond $maxAverageSpeed,
        int $paceZone,
    ): self {
        return new self(
            activityId: $activityId,
            unitSystem: $unitSystem,
            splitNumber: $splitNumber,
            distance: $distance,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            movingTimeInSeconds: $movingTimeInSeconds,
            elevationDifference: $elevationDifference,
            averageSpeed: $averageSpeed,
            minAverageSpeed: $minAverageSpeed,
            maxAverageSpeed: $maxAverageSpeed,
            paceZone: $paceZone,
            gapPaceInSecondsPerKm: null,
        );
    }

    public static function fromState(
        ActivityId $activityId,
        UnitSystem $unitSystem,
        int $splitNumber,
        Meter $distance,
        int $elapsedTimeInSeconds,
        int $movingTimeInSeconds,
        Meter $elevationDifference,
        MetersPerSecond $averageSpeed,
        MetersPerSecond $minAverageSpeed,
        MetersPerSecond $maxAverageSpeed,
        int $paceZone,
        ?SecPerKm $gapPaceInSecondsPerKm = null,
    ): self {
        return new self(
            activityId: $activityId,
            unitSystem: $unitSystem,
            splitNumber: $splitNumber,
            distance: $distance,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            movingTimeInSeconds: $movingTimeInSeconds,
            elevationDifference: $elevationDifference,
            averageSpeed: $averageSpeed,
            minAverageSpeed: $minAverageSpeed,
            maxAverageSpeed: $maxAverageSpeed,
            paceZone: $paceZone,
            gapPaceInSecondsPerKm: $gapPaceInSecondsPerKm,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getUnitSystem(): UnitSystem
    {
        return $this->unitSystem;
    }

    public function getSplitNumber(): int
    {
        return $this->splitNumber;
    }

    public function getDistance(): Meter
    {
        return $this->distance;
    }

    public function getElapsedTimeInSeconds(): int
    {
        return $this->elapsedTimeInSeconds;
    }

    public function getMovingTimeInSeconds(): int
    {
        return $this->movingTimeInSeconds;
    }

    public function getElevationDifference(): Meter
    {
        return $this->elevationDifference;
    }

    public function getAverageSpeed(): MetersPerSecond
    {
        return $this->averageSpeed;
    }

    public function getRelativePacePercentage(): float
    {
        $minAverageSpeed = $this->getMinAverageSpeed()->toFloat();
        $maxAverageSpeed = $this->getMaxAverageSpeed()->toFloat();

        if ($maxAverageSpeed <= 0.0) {
            return 0.0;
        }

        if ($maxAverageSpeed === $minAverageSpeed) {
            return 0.0;
        }

        $min = $minAverageSpeed * 0.85;
        $max = $maxAverageSpeed * 1.05;
        $averageSpeed = $this->getAverageSpeed()->toFloat();

        $relative = ($averageSpeed - $min) / ($max - $min) * 100;
        $relative = max(0, min(100, $relative));

        return round($relative, 2);
    }

    public function getMinAverageSpeed(): MetersPerSecond
    {
        return $this->minAverageSpeed;
    }

    public function getMaxAverageSpeed(): MetersPerSecond
    {
        return $this->maxAverageSpeed;
    }

    public function getPaceInSecPerKm(): SecPerKm
    {
        return $this->getAverageSpeed()->toSecPerKm();
    }

    public function getPaceInSecPer100Meter(): SecPer100Meter
    {
        return $this->getAverageSpeed()->toSecPerKm()->toSecPer100Meter();
    }

    public function getPaceZone(): int
    {
        return $this->paceZone;
    }

    public function withGapPace(SecPerKm $gapPace): self
    {
        return clone ($this, [
            'gapPaceInSecondsPerKm' => $gapPace,
        ]);
    }

    public function getGapPaceInSecondsPerKm(): ?SecPerKm
    {
        return $this->gapPaceInSecondsPerKm;
    }

    public function exportForAITooling(): array
    {
        return [
            'activityId' => $this->getActivityId()->toUnprefixedString(),
            'unitSystem' => $this->getUnitSystem()->value,
            'splitNumber' => $this->getSplitNumber(),
            'elapsedTimeInSeconds' => $this->getElapsedTimeInSeconds(),
            'movingTimeInSeconds' => $this->getMovingTimeInSeconds(),
            'distanceInMeter' => $this->getDistance(),
            'averageSpeedInMetersPerSecond' => $this->getAverageSpeed(),
            'elevationDifferenceInMeter' => $this->getElevationDifference(),
            'paceZone' => $this->getPaceZone(),
        ];
    }
}

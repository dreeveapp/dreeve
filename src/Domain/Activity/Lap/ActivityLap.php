<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Domain\Activity\ActivityId;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'ActivitySplit_activityId', columns: ['activityId'])]
final readonly class ActivityLap implements SupportsAITooling
{
    use ProvideTimeFormats;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private ActivityLapId $lapId,
        #[ORM\Column(type: 'string')]
        private ActivityId $activityId,
        #[ORM\Column(type: 'integer')]
        private int $lapNumber,
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Column(type: 'integer')]
        private int $elapsedTimeInSeconds,
        #[ORM\Column(type: 'integer')]
        private int $movingTimeInSeconds,
        #[ORM\Column(type: 'integer')]
        private Meter $distance,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $averageSpeed,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $minAverageSpeed,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $maxAverageSpeed,
        #[ORM\Column(type: 'float')]
        private MetersPerSecond $maxSpeed,
        #[ORM\Column(type: 'integer')]
        private Meter $elevationDifference,
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $averageHeartRate,
    ) {
    }

    public static function create(
        ActivityLapId $lapId,
        ActivityId $activityId,
        int $lapNumber,
        string $name,
        int $elapsedTimeInSeconds,
        int $movingTimeInSeconds,
        Meter $distance,
        MetersPerSecond $averageSpeed,
        MetersPerSecond $minAverageSpeed,
        MetersPerSecond $maxAverageSpeed,
        MetersPerSecond $maxSpeed,
        Meter $elevationDifference,
        ?int $averageHeartRate,
    ): self {
        return new self(
            lapId: $lapId,
            activityId: $activityId,
            lapNumber: $lapNumber,
            name: $name,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            movingTimeInSeconds: $movingTimeInSeconds,
            distance: $distance,
            averageSpeed: $averageSpeed,
            minAverageSpeed: $minAverageSpeed,
            maxAverageSpeed: $maxAverageSpeed,
            maxSpeed: $maxSpeed,
            elevationDifference: $elevationDifference,
            averageHeartRate: $averageHeartRate,
        );
    }

    public static function fromState(
        ActivityLapId $lapId,
        ActivityId $activityId,
        int $lapNumber,
        string $name,
        int $elapsedTimeInSeconds,
        int $movingTimeInSeconds,
        Meter $distance,
        MetersPerSecond $averageSpeed,
        MetersPerSecond $minAverageSpeed,
        MetersPerSecond $maxAverageSpeed,
        MetersPerSecond $maxSpeed,
        Meter $elevationDifference,
        ?int $averageHeartRate,
    ): self {
        return new self(
            lapId: $lapId,
            activityId: $activityId,
            lapNumber: $lapNumber,
            name: $name,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            movingTimeInSeconds: $movingTimeInSeconds,
            distance: $distance,
            averageSpeed: $averageSpeed,
            minAverageSpeed: $minAverageSpeed,
            maxAverageSpeed: $maxAverageSpeed,
            maxSpeed: $maxSpeed,
            elevationDifference: $elevationDifference,
            averageHeartRate: $averageHeartRate,
        );
    }

    public function getLapId(): ActivityLapId
    {
        return $this->lapId;
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getLapNumber(): int
    {
        return $this->lapNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getElapsedTimeInSeconds(): int
    {
        return $this->elapsedTimeInSeconds;
    }

    public function getMovingTimeInSeconds(): int
    {
        return $this->movingTimeInSeconds;
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsClock($this->getMovingTimeInSeconds());
    }

    public function getDistance(): Meter
    {
        return $this->distance;
    }

    public function getDistanceInKilometer(): Kilometer
    {
        return $this->getDistance()->toKilometer();
    }

    public function getPaceInSecPerKm(): SecPerKm
    {
        return $this->getAverageSpeed()->toSecPerKm();
    }

    public function getPaceInSecPer100Meter(): SecPer100Meter
    {
        return $this->getAverageSpeed()->toSecPerKm()->toSecPer100Meter();
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

    public function getMaxSpeed(): MetersPerSecond
    {
        return $this->maxSpeed;
    }

    public function getElevationDifference(): Meter
    {
        return $this->elevationDifference;
    }

    public function getAverageHeartRate(): ?int
    {
        return $this->averageHeartRate;
    }

    /**
     * @return array<string, mixed>
     */
    public function exportForAITooling(): array
    {
        return [
            'id' => $this->getLapId()->toUnprefixedString(),
            'activityId' => $this->getActivityId()->toUnprefixedString(),
            'lapNumber' => $this->getLapNumber(),
            'elapsedTimeInSeconds' => $this->getElapsedTimeInSeconds(),
            'movingTimeInSeconds' => $this->getMovingTimeInSeconds(),
            'distanceInMeter' => $this->getDistance(),
            'averageSpeedInMetersPerSecond' => $this->getAverageSpeed(),
            'maxSpeedInMetersPerSecond' => $this->getMaxSpeed(),
            'elevationDifferenceInMeter' => $this->getElevationDifference(),
            'averageHeartRate' => $this->getAverageHeartRate(),
        ];
    }
}

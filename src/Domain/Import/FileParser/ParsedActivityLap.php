<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser;

use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;

final readonly class ParsedActivityLap
{
    private function __construct(
        private int $lapNumber,
        private string $name,
        private int $elapsedTimeInSeconds,
        private int $movingTimeInSeconds,
        private Meter $distance,
        private MetersPerSecond $averageSpeed,
        private MetersPerSecond $maxSpeed,
        private Meter $elevationDifference,
        private ?int $averageHeartRate,
    ) {
    }

    public static function create(
        int $lapNumber,
        string $name,
        int $elapsedTimeInSeconds,
        int $movingTimeInSeconds,
        Meter $distance,
        MetersPerSecond $averageSpeed,
        MetersPerSecond $maxSpeed,
        Meter $elevationDifference,
        ?int $averageHeartRate,
    ): self {
        return new self(
            lapNumber: $lapNumber,
            name: $name,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            movingTimeInSeconds: $movingTimeInSeconds,
            distance: $distance,
            averageSpeed: $averageSpeed,
            maxSpeed: $maxSpeed,
            elevationDifference: $elevationDifference,
            averageHeartRate: $averageHeartRate,
        );
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

    public function getDistance(): Meter
    {
        return $this->distance;
    }

    public function getAverageSpeed(): MetersPerSecond
    {
        return $this->averageSpeed;
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
}

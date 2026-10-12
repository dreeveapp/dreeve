<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance;

use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class StartingValue implements \JsonSerializable
{
    private function __construct(
        private Meter $distance,
        private Seconds $movingTime,
    ) {
    }

    public static function create(
        Meter $distance,
        Seconds $movingTime,
    ): self {
        return new self(
            distance: $distance,
            movingTime: $movingTime,
        );
    }

    public function getDistance(): Meter
    {
        return $this->distance;
    }

    public function getMovingTime(): Seconds
    {
        return $this->movingTime;
    }

    /**
     * @return array{distanceInMeter: int, movingTimeInSeconds: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'distanceInMeter' => $this->distance->toInt(),
            'movingTimeInSeconds' => $this->movingTime->toInt(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindTotalsPerMonth;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;

final readonly class FindTotalsPerMonthResponse implements Response
{
    public function __construct(
        /** @var array<int, array{0: int, 1: SportType, 2: Kilometer}> */
        private array $distancePerMonth,
        /** @var array<int, array{0: int, 1: SportType, 2: Meter}> */
        private array $elevationPerMonth,
        /** @var array<int, array{0: int, 1: SportType, 2: int}> */
        private array $movingTimePerMonth,
        private Kilometer $totalDistance,
        private Meter $totalElevation,
        private int $totalMovingTime,
    ) {
    }

    /**
     * @return array<int, array{0: int, 1: SportType, 2: Kilometer}>
     */
    public function getDistancePerMonth(): array
    {
        return $this->distancePerMonth;
    }

    /**
     * @return array<int, array{0: int, 1: SportType, 2: Meter}>
     */
    public function getElevationPerMonth(): array
    {
        return $this->elevationPerMonth;
    }

    /**
     * @return array<int, array{0: int, 1: SportType, 2: int}>
     */
    public function getMovingTimePerMonth(): array
    {
        return $this->movingTimePerMonth;
    }

    public function getTotalDistance(): Kilometer
    {
        return $this->totalDistance;
    }

    public function getTotalElevation(): Meter
    {
        return $this->totalElevation;
    }

    public function getTotalMovingTime(): int
    {
        return $this->totalMovingTime;
    }
}

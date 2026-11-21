<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

use App\Infrastructure\Measurement\Length\Kilometer;

final readonly class ActivityLapsSummary
{
    private const int MIN_VALID_LAP_DISTANCE_IN_METER = 1000;

    private function __construct(
        private ActivityLaps $laps,
    ) {
    }

    public static function create(ActivityLaps $laps): self
    {
        return new self($laps);
    }

    public function getNumberOfLaps(): int
    {
        return count($this->laps);
    }

    public function getNumberOfValidLaps(): int
    {
        return count($this->validLaps());
    }

    public function getTotalDistanceInKilometer(): Kilometer
    {
        return Kilometer::from($this->laps->sum(
            static fn (ActivityLap $lap): float => $lap->getDistance()->toFloat()
        ) / 1000);
    }

    public function getAverageLapDistanceInKilometer(): ?Kilometer
    {
        $validLaps = $this->validLaps();
        if ($validLaps->isEmpty()) {
            return null;
        }

        return Kilometer::from($validLaps->sum(
            static fn (ActivityLap $lap): float => $lap->getDistance()->toFloat()
        ) / count($validLaps) / 1000);
    }

    public function getFastestLap(): ?ActivityLap
    {
        $fastestLap = null;
        foreach ($this->validLaps() as $lap) {
            if (!$fastestLap instanceof ActivityLap
                || $lap->getAverageSpeed()->toFloat() > $fastestLap->getAverageSpeed()->toFloat()) {
                $fastestLap = $lap;
            }
        }

        return $fastestLap;
    }

    /**
     * The variation coefficient of the lap moving times, expressed as a percentage.
     * The lower, the more regular the laps were ridden.
     */
    public function getRegularityPercentage(): ?float
    {
        $validLaps = $this->validLaps();
        if (count($validLaps) < 2) {
            return null;
        }

        $movingTimes = $validLaps->map(
            static fn (ActivityLap $lap): float => (float) $lap->getMovingTimeInSeconds()
        );
        $average = array_sum($movingTimes) / count($movingTimes);
        if ($average <= 0.0) {
            return null;
        }

        $variance = array_sum(array_map(
            static fn (float $movingTime): float => ($movingTime - $average) ** 2,
            $movingTimes
        )) / count($movingTimes);

        return round(sqrt($variance) / $average * 100, 1);
    }

    private function validLaps(): ActivityLaps
    {
        return $this->laps->filter(
            static fn (ActivityLap $lap): bool => $lap->getDistance()->toInt() >= self::MIN_VALID_LAP_DISTANCE_IN_METER
        );
    }
}

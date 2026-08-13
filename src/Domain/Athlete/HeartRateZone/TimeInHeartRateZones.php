<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

final readonly class TimeInHeartRateZones
{
    private int $totalTimeInSeconds;

    private function __construct(
        private int $timeInZoneOne,
        private int $timeInZoneTwo,
        private int $timeInZoneThree,
        private int $timeInZoneFour,
        private int $timeInZoneFive,
    ) {
        $this->totalTimeInSeconds = $this->getTimeInZoneOne() + $this->getTimeInZoneTwo()
            + $this->getTimeInZoneThree() + $this->getTimeInZoneFour() + $this->getTimeInZoneFive();
    }

    public static function create(
        int $timeInZoneOne,
        int $timeInZoneTwo,
        int $timeInZoneThree,
        int $timeInZoneFour,
        int $timeInZoneFive,
    ): self {
        return new self(
            timeInZoneOne: $timeInZoneOne,
            timeInZoneTwo: $timeInZoneTwo,
            timeInZoneThree: $timeInZoneThree,
            timeInZoneFour: $timeInZoneFour,
            timeInZoneFive: $timeInZoneFive,
        );
    }

    public function getTimeInZoneOne(): int
    {
        return $this->timeInZoneOne;
    }

    public function getPercentageInZoneOne(): float
    {
        return round($this->calculatePercentage($this->getTimeInZoneOne()), 1);
    }

    public function getTimeInZoneTwo(): int
    {
        return $this->timeInZoneTwo;
    }

    public function getPercentageInZoneTwo(): float
    {
        return round($this->calculatePercentage($this->getTimeInZoneTwo()), 1);
    }

    public function getTimeInZoneThree(): int
    {
        return $this->timeInZoneThree;
    }

    public function getPercentageInZoneThree(): float
    {
        return round($this->calculatePercentage($this->getTimeInZoneThree()), 1);
    }

    public function getTimeInZoneFour(): int
    {
        return $this->timeInZoneFour;
    }

    public function getPercentageInZoneFour(): float
    {
        return round($this->calculatePercentage($this->getTimeInZoneFour()), 1);
    }

    public function getTimeInZoneFive(): int
    {
        return $this->timeInZoneFive;
    }

    public function getPercentageInZoneFive(): float
    {
        return round($this->calculatePercentage($this->getTimeInZoneFive()), 1);
    }

    public function getTotalTimeInSeconds(): int
    {
        return $this->totalTimeInSeconds;
    }

    public function getPercentageInLowZones(): float
    {
        return $this->calculatePercentage($this->getTimeInZoneOne() + $this->getTimeInZoneTwo());
    }

    public function getPercentageInMediumZone(): float
    {
        return $this->calculatePercentage($this->getTimeInZoneThree());
    }

    public function getPercentageInHighZones(): float
    {
        return $this->calculatePercentage($this->getTimeInZoneFour() + $this->getTimeInZoneFive());
    }

    private function calculatePercentage(int $number): float
    {
        if (0 === $this->getTotalTimeInSeconds()) {
            return 0;
        }

        return $number / $this->getTotalTimeInSeconds() * 100;
    }
}

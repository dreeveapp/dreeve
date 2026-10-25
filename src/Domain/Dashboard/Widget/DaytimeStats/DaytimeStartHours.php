<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

final readonly class DaytimeStartHours
{
    /**
     * @param array<string, int> $startHours
     */
    private function __construct(
        private array $startHours,
    ) {
    }

    public static function create(
        int $morning,
        int $afternoon,
        int $evening,
        int $night,
    ): self {
        return new self([
            Daytime::MORNING->value => $morning,
            Daytime::AFTERNOON->value => $afternoon,
            Daytime::EVENING->value => $evening,
            Daytime::NIGHT->value => $night,
        ]);
    }

    public function getStartHour(Daytime $daytime): int
    {
        return $this->startHours[$daytime->value];
    }

    public function getEndHour(Daytime $daytime): int
    {
        $daytimes = Daytime::cases();

        return $this->getStartHour($daytimes[(array_search($daytime, $daytimes, true) + 1) % count($daytimes)]);
    }

    public function findDaytime(int $hour): Daytime
    {
        $found = Daytime::MORNING;
        foreach (Daytime::cases() as $daytime) {
            if ($this->hoursSinceMorning($this->getStartHour($daytime)) <= $this->hoursSinceMorning($hour)) {
                $found = $daytime;
            }
        }

        return $found;
    }

    /**
     * @return int[]
     */
    public function getHoursInDisplayOrder(): array
    {
        return array_map(
            fn (int $offset): int => ($this->getStartHour(Daytime::MORNING) + $offset) % 24,
            range(0, 23),
        );
    }

    private function hoursSinceMorning(int $hour): int
    {
        return ($hour - $this->getStartHour(Daytime::MORNING) + 24) % 24;
    }
}

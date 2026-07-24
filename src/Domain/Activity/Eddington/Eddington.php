<?php

namespace App\Domain\Activity\Eddington;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class Eddington
{
    /** @var array<string, Eddington> */
    public static array $instances = [];

    private const string DATE_FORMAT = 'Y-m-d';
    /** @var array<string, int|float> */
    private readonly array $distancesPerDay;
    private readonly int $eddingtonNumber;

    private function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly Activities $activities,
        private readonly UnitSystem $unitSystem,
        private readonly EddingtonConfigItem $config,
    ) {
        $this->distancesPerDay = $this->buildDistancesPerDay();
        $this->eddingtonNumber = $this->calculateEddingtonNumber();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getConfig(): EddingtonConfigItem
    {
        return $this->config;
    }

    public function getUnitSystem(): UnitSystem
    {
        return $this->unitSystem;
    }

    /**
     * @return array<string, float|int>
     */
    private function buildDistancesPerDay(): array
    {
        $distancesPerDay = [];
        foreach ($this->activities as $activity) {
            $day = $activity->getStartDate()->format(self::DATE_FORMAT);
            if (!array_key_exists($day, $distancesPerDay)) {
                $distancesPerDay[$day] = 0;
            }

            $distance = $activity->getDistance()->toUnitSystem($this->unitSystem);
            $distancesPerDay[$day] += $distance->toFloat();
        }

        return $distancesPerDay;
    }

    private function calculateEddingtonNumber(): int
    {
        $distanceCounts = [];

        foreach ($this->distancesPerDay as $distance) {
            $rounded = (int) floor($distance);
            for ($i = 1; $i <= $rounded; ++$i) {
                $distanceCounts[$i] = ($distanceCounts[$i] ?? 0) + 1;
            }
        }

        ksort($distanceCounts);

        $eddington = 0;
        foreach ($distanceCounts as $distance => $count) {
            if ($count >= $distance) {
                $eddington = $distance;
            } else {
                break;
            }
        }

        return $eddington;
    }

    /**
     * @return array<string, float|int>
     */
    private function getDistancesPerDay(): array
    {
        return $this->distancesPerDay;
    }

    public function getLongestDistanceInADay(): int
    {
        if ([] === $this->getDistancesPerDay()) {
            return 0;
        }

        return (int) floor(max($this->getDistancesPerDay()));
    }

    /**
     * @return array<int<1, max>, int<0, max>>
     */
    public function getTimesCompletedData(): array
    {
        $counts = [];

        foreach ($this->distancesPerDay as $distance) {
            $rounded = (int) floor($distance);
            for ($i = 1; $i <= $rounded; ++$i) {
                $counts[$i] = ($counts[$i] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    public function getNumber(): int
    {
        return $this->eddingtonNumber;
    }

    /**
     * @return array<int, int>
     */
    public function getDaysToCompleteForFutureNumbers(): array
    {
        $futureNumbers = [];
        $eddingtonNumber = $this->getNumber();
        $timesCompleted = $this->getTimesCompletedData();
        for ($distance = $eddingtonNumber + 1; $distance <= $this->getLongestDistanceInADay(); ++$distance) {
            $futureNumbers[$distance] = $distance - $timesCompleted[$distance];
        }

        return $futureNumbers;
    }

    /**
     * @return array<int, SerializableDateTime>
     */
    public function getEddingtonHistory(): array
    {
        $history = [];
        $eddingtonNumber = $this->getNumber();
        // We need the distances sorted by oldest => newest.
        $distancesPerDay = array_reverse($this->getDistancesPerDay());

        for ($distance = $eddingtonNumber; $distance > 0; --$distance) {
            $countForDistance = 0;
            foreach ($distancesPerDay as $day => $distanceInDay) {
                if ($distanceInDay >= $distance) {
                    ++$countForDistance;
                }
                if ($countForDistance === $distance) {
                    // This is the day we reached the eddington Number.
                    $history[$distance] = SerializableDateTime::fromString($day);
                    break;
                }
            }
        }

        return array_reverse($history, true);
    }

    public static function getInstance(
        Activities $activities,
        EddingtonConfigItem $config,
        UnitSystem $unitSystem,
    ): self {
        $eddingtonId = $config->getId().$unitSystem->value;

        if (array_key_exists($eddingtonId, self::$instances)) {
            return self::$instances[$eddingtonId];
        }

        self::$instances[$eddingtonId] = new self(
            id: $eddingtonId,
            label: $config->getLabel(),
            activities: $activities,
            unitSystem: $unitSystem,
            config: $config
        );

        return self::$instances[$eddingtonId];
    }
}

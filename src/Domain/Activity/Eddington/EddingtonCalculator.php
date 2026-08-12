<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington;

use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Domain\Activity\Eddington\FindDistancePerDay\FindDistancePerDay;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class EddingtonCalculator
{
    public function __construct(
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return list<Eddington>
     */
    public function calculate(UnitSystem ...$unitSystems): array
    {
        $eddingtons = [];
        foreach ($this->settingsRepository->metrics()->getEddingtonConfiguration() as $eddingtonConfigItem) {
            $distancePerDay = $this->queryBus->ask(
                new FindDistancePerDay($eddingtonConfigItem->getSportTypesToInclude())
            )->getDistancePerDay();

            if ([] === $distancePerDay) {
                continue;
            }

            foreach ($unitSystems as $unitSystem) {
                $eddington = $this->calculateFor(
                    config: $eddingtonConfigItem,
                    unitSystem: $unitSystem,
                    distancePerDay: $distancePerDay
                );
                if ($eddington->getNumber() <= 0) {
                    continue;
                }
                $eddingtons[] = $eddington;
            }
        }

        return $eddingtons;
    }

    /**
     * @param array<string, Meter> $distancePerDay
     */
    private function calculateFor(
        EddingtonConfigItem $config,
        UnitSystem $unitSystem,
        array $distancePerDay,
    ): Eddington {
        // The number of days on which exactly x units were covered.
        $numberOfDaysPerDistance = [];
        // The number of days processed so far on which at least $nextNumber units were covered.
        $numberOfDaysAtOrAboveNextNumber = 0;
        $nextNumber = 1;
        $eddingtonNumber = 0;
        $longestDistanceInADay = 0;
        $history = [];

        foreach ($distancePerDay as $day => $distance) {
            $distanceInADay = (int) floor($distance->toKilometer()->toUnitSystem($unitSystem)->toFloat());
            $longestDistanceInADay = max($longestDistanceInADay, $distanceInADay);
            $numberOfDaysPerDistance[$distanceInADay] = ($numberOfDaysPerDistance[$distanceInADay] ?? 0) + 1;

            if ($distanceInADay >= $nextNumber) {
                ++$numberOfDaysAtOrAboveNextNumber;
            }

            while ($numberOfDaysAtOrAboveNextNumber >= $nextNumber) {
                $eddingtonNumber = $nextNumber;
                $history[$nextNumber] = SerializableDateTime::fromString($day);
                $numberOfDaysAtOrAboveNextNumber -= $numberOfDaysPerDistance[$nextNumber] ?? 0;
                ++$nextNumber;
            }
        }

        $timesCompletedData = $this->calculateTimesCompletedData(
            numberOfDaysPerDistance: $numberOfDaysPerDistance,
            longestDistanceInADay: $longestDistanceInADay
        );

        $daysToCompleteForFutureNumbers = [];
        for ($distance = $eddingtonNumber + 1; $distance <= $longestDistanceInADay; ++$distance) {
            $daysToCompleteForFutureNumbers[$distance] = $distance - $timesCompletedData[$distance];
        }

        return Eddington::create(
            config: $config,
            unitSystem: $unitSystem,
            eddingtonNumber: $eddingtonNumber,
            longestDistanceInADay: $longestDistanceInADay,
            timesCompletedData: $timesCompletedData,
            history: $history,
            daysToCompleteForFutureNumbers: $daysToCompleteForFutureNumbers
        );
    }

    /**
     * @param array<int, int<0, max>> $numberOfDaysPerDistance
     *
     * @return array<int<1, max>, int<0, max>>
     */
    private function calculateTimesCompletedData(array $numberOfDaysPerDistance, int $longestDistanceInADay): array
    {
        $timesCompletedData = [];
        $timesCompleted = 0;

        for ($distance = $longestDistanceInADay; $distance >= 1; --$distance) {
            $timesCompleted += $numberOfDaysPerDistance[$distance] ?? 0;
            $timesCompletedData[$distance] = $timesCompleted;
        }

        return array_reverse($timesCompletedData, preserve_keys: true);
    }
}

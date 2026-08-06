<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\BestEffort\FindBestEfforts\FindBestEfforts;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class BestEffortsCalculator
{
    private const int HISTORY_SIZE = 10;

    public function __construct(
        private QueryBus $queryBus,
        private SportTypeRepository $sportTypeRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private Clock $clock,
    ) {
    }

    public function calculate(): BestEfforts
    {
        $response = $this->queryBus->ask(new FindBestEfforts());
        $startDateTimePerActivity = $response->getStartDateTimePerActivity();

        $periodBoundaries = [];
        foreach (BestEffortPeriod::cases() as $period) {
            $periodBoundaries[$period->value] = $this->boundariesFor(
                $period->getDateRange($this->clock->getCurrentDateTimeImmutable())
            );
        }

        $bestEffortPerPeriod = [];
        $historyPerSportType = [];

        // The efforts come in fastest first, so the first effort that falls within a period is that
        // period's best effort, and the first ten of a distance are its all time top ten.
        foreach ($response->getBestEfforts() as $bestEffort) {
            $sportType = $bestEffort->getSportType()->value;
            $distanceInMeter = $bestEffort->getDistanceInMeter()->toInt();
            $startDateTime = $startDateTimePerActivity[(string) $bestEffort->getActivityId()];

            if (count($historyPerSportType[$sportType][$distanceInMeter] ?? []) < self::HISTORY_SIZE) {
                $historyPerSportType[$sportType][$distanceInMeter][] = $bestEffort;
            }

            foreach ($periodBoundaries as $periodValue => [$from, $till]) {
                if (isset($bestEffortPerPeriod[$periodValue][$sportType][$distanceInMeter])) {
                    continue;
                }
                if ($startDateTime->isBefore($from)) {
                    continue;
                }
                if ($startDateTime->isAfter($till)) {
                    continue;
                }
                $bestEffortPerPeriod[$periodValue][$sportType][$distanceInMeter] = $bestEffort;
            }
        }

        $sportTypesPerPeriod = $this->buildSportTypesPerPeriod($bestEffortPerPeriod);

        return BestEfforts::create(
            bestEffortPerPeriod: $bestEffortPerPeriod,
            historyPerSportType: $historyPerSportType,
            periods: array_values(array_filter(
                BestEffortPeriod::cases(),
                fn (BestEffortPeriod $period): bool => isset($bestEffortPerPeriod[$period->value])
            )),
            sportTypesPerPeriod: $sportTypesPerPeriod,
            activityTypes: $this->buildActivityTypes($sportTypesPerPeriod),
        );
    }

    /**
     * @param array<string, array<string, array<int, ActivityBestEffort>>> $bestEffortPerPeriod
     *
     * @return array<string, array<string, SportTypes>>
     */
    private function buildSportTypesPerPeriod(array $bestEffortPerPeriod): array
    {
        $importedSportTypes = $this->sportTypeRepository->findAll();

        $sportTypesPerPeriod = [];
        foreach (BestEffortPeriod::cases() as $period) {
            foreach ($importedSportTypes as $sportType) {
                if (empty($bestEffortPerPeriod[$period->value][$sportType->value])) {
                    continue;
                }
                $activityType = $sportType->getActivityType();
                $sportTypesPerPeriod[$period->value][$activityType->value] ??= SportTypes::empty();
                $sportTypesPerPeriod[$period->value][$activityType->value]->add($sportType);
            }
        }

        return $sportTypesPerPeriod;
    }

    /**
     * @param array<string, array<string, SportTypes>> $sportTypesPerPeriod
     */
    private function buildActivityTypes(array $sportTypesPerPeriod): ActivityTypes
    {
        $activityTypes = ActivityTypes::empty();
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            foreach ($sportTypesPerPeriod as $sportTypesPerActivityType) {
                if (!isset($sportTypesPerActivityType[$activityType->value])) {
                    continue;
                }
                $activityTypes->add($activityType);
                break;
            }
        }

        return $activityTypes;
    }

    /**
     * @return array{SerializableDateTime, SerializableDateTime}
     */
    private function boundariesFor(DateRange $dateRange): array
    {
        return [
            SerializableDateTime::fromString($dateRange->getFrom()->format('Y-m-d 00:00:00')),
            SerializableDateTime::fromString($dateRange->getTill()->format('Y-m-d 23:59:59')),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Challenge\Consistency\ProvideGoalConverters;
use App\Infrastructure\ValueObject\Measurement\ProvideUnitFromScalar;
use App\Infrastructure\ValueObject\Measurement\Unit;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class TrainingGoal
{
    use ProvideUnitFromScalar;
    use ProvideGoalConverters;

    private function __construct(
        private string $label,
        private TrainingGoalType $type,
        private TrainingGoalPeriod $period,
        private Unit $goal,
        private SportTypes $sportTypesToInclude,
        private ?DateRange $restrictToDateRange = null,
    ) {
    }

    public static function create(
        string $label,
        TrainingGoalType $type,
        TrainingGoalPeriod $period,
        float $goal,
        string $unit,
        SportTypes $sportTypesToInclude,
        ?DateRange $restrictToDateRange = null,
    ): self {
        return new self(
            label: $label,
            type: $type,
            period: $period,
            goal: self::createUnitFromScalars(
                value: $goal,
                unit: $unit,
            ),
            sportTypesToInclude: $sportTypesToInclude,
            restrictToDateRange: $restrictToDateRange,
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): TrainingGoalType
    {
        return $this->type;
    }

    public function getPeriod(): TrainingGoalPeriod
    {
        return $this->period;
    }

    public function getGoal(): Unit
    {
        return $this->goal;
    }

    public function getSportTypesToInclude(): SportTypes
    {
        return $this->sportTypesToInclude;
    }

    public function isActiveOn(SerializableDateTime $date): bool
    {
        if (!$this->restrictToDateRange instanceof DateRange) {
            return true;
        }

        return $date->isAfterOrOn($this->restrictToDateRange->getFrom())
            && $date->isBeforeOrOn($this->restrictToDateRange->getTill());
    }
}

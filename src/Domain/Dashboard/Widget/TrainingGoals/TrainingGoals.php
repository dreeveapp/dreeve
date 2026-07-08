<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Infrastructure\ValueObject\Collection;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

/**
 * @extends Collection<TrainingGoal>
 */
final class TrainingGoals extends Collection
{
    public function getItemClassName(): string
    {
        return TrainingGoal::class;
    }

    /**
     * @param array<string, mixed> $items
     */
    public static function fromConfig(array $items): self
    {
        if ([] === $items) {
            return self::empty();
        }

        $trainingGoals = [];

        foreach (array_keys($items) as $trainingGoalPeriod) {
            if (TrainingGoalPeriod::tryFrom($trainingGoalPeriod)) {
                continue;
            }

            throw new InvalidDashboardLayout(sprintf('"%s" is not a valid goal period', $trainingGoalPeriod));
        }

        foreach ($items as $period => $periodGoalConfig) {
            $trainingGoalPeriod = TrainingGoalPeriod::from($period);
            foreach ($periodGoalConfig as $goalConfig) {
                if (!is_array($goalConfig)) {
                    throw new InvalidDashboardLayout('Invalid TrainingGoals configuration provided');
                }

                foreach (['label', 'type', 'unit', 'goal', 'sportTypesToInclude'] as $requiredKey) {
                    if (array_key_exists($requiredKey, $goalConfig)) {
                        continue;
                    }
                    throw new InvalidDashboardLayout(sprintf('"%s" property is required', $requiredKey));
                }

                if (empty($goalConfig['label'])) {
                    throw new InvalidDashboardLayout('"label" property cannot be empty');
                }

                if (!is_numeric($goalConfig['goal'])) {
                    throw new InvalidDashboardLayout('"goal" property must be a valid number');
                }

                if (!$type = TrainingGoalType::tryFrom($goalConfig['type'])) {
                    throw new InvalidDashboardLayout(sprintf('"%s" is not a valid goalType', $goalConfig['type']));
                }

                if (!is_array($goalConfig['sportTypesToInclude'])) {
                    throw new InvalidDashboardLayout('"sportTypesToInclude" property must be an array');
                }

                if (empty($goalConfig['sportTypesToInclude'])) {
                    throw new InvalidDashboardLayout('"sportTypesToInclude" property cannot be empty');
                }

                $sportTypesToInclude = SportTypes::empty();
                foreach ($goalConfig['sportTypesToInclude'] as $sportTypeToInclude) {
                    if (!$sportType = SportType::tryFrom($sportTypeToInclude)) {
                        throw new InvalidDashboardLayout(sprintf('"%s" is not a valid sport type', $sportTypeToInclude));
                    }
                    $sportTypesToInclude->add($sportType);
                }

                if (in_array($type, TrainingGoalType::lengthRelated()) && !in_array($goalConfig['unit'], [
                    TrainingGoal::KILOMETER,
                    TrainingGoal::METER,
                    TrainingGoal::MILES,
                    TrainingGoal::FOOT,
                ])) {
                    throw new InvalidDashboardLayout(sprintf('The unit "%s" is not valid for goal type "%s"', $goalConfig['unit'], $type->value));
                }

                if (TrainingGoalType::MOVING_TIME === $type && !in_array($goalConfig['unit'], [
                    TrainingGoal::HOUR,
                    TrainingGoal::MINUTE,
                ])) {
                    throw new InvalidDashboardLayout(sprintf('The unit "%s" is not valid for goal type "%s"', $goalConfig['unit'], $type->value));
                }

                if (in_array($type, TrainingGoalType::simpleUnitRelated())) {
                    // Hardcode the unit.
                    $goalConfig['unit'] = TrainingGoal::SIMPLE;
                }

                $restrictToDateRange = null;
                if (array_key_exists('restrictToDateRange', $goalConfig) && (!empty($goalConfig['restrictToDateRange']['from']) || !empty($goalConfig['restrictToDateRange']['to']))) {
                    $dateRangeConfig = $goalConfig['restrictToDateRange'];
                    if (empty($dateRangeConfig['from']) || empty($dateRangeConfig['to'])) {
                        throw new InvalidDashboardLayout('"restrictToDateRange" requires both "from" and "to" keys');
                    }
                    try {
                        $restrictToDateRange = DateRange::fromDates(
                            from: SerializableDateTime::fromString($dateRangeConfig['from']),
                            till: SerializableDateTime::fromString($dateRangeConfig['to'].' 23:59:59'),
                        );
                    } catch (\DateMalformedStringException) {
                        throw new InvalidDashboardLayout('"restrictToDateRange" contains invalid date values, expected format "YYYY-MM-DD"');
                    } catch (\InvalidArgumentException) {
                        throw new InvalidDashboardLayout('"restrictToDateRange.from" must be before or equal to "restrictToDateRange.to"');
                    }
                }

                $trainingGoals[] = TrainingGoal::create(
                    label: $goalConfig['label'],
                    type: $type,
                    period: $trainingGoalPeriod,
                    goal: (float) $goalConfig['goal'],
                    unit: $goalConfig['unit'],
                    sportTypesToInclude: $sportTypesToInclude,
                    restrictToDateRange: $restrictToDateRange,
                );
            }
        }

        return self::fromArray($trainingGoals);
    }
}

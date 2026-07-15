<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;

final readonly class WeekdayCondition implements Condition
{
    public function getLabel(): string
    {
        return 'Weekday';
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--weekday';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::IS_ONE_OF->value,
            'weekdays' => [],
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForSet()) {
            throw new InvalidAutomationRule(sprintf('Invalid weekday operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $weekdays = $configuration->get('weekdays');
        if (!is_array($weekdays) || [] === $weekdays) {
            throw new InvalidAutomationRule('At least one weekday is required.');
        }

        foreach ($weekdays as $weekday) {
            $isValidIsoWeekday = (is_int($weekday) || (is_string($weekday) && ctype_digit($weekday)))
                && (int) $weekday >= 1 && (int) $weekday <= 7;
            if (!$isValidIsoWeekday) {
                throw new InvalidAutomationRule(sprintf('Invalid weekday "%s", expected 1 (Monday) through 7 (Sunday).', is_scalar($weekday) ? (string) $weekday : ''));
            }
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->get('operator');
        $weekdays = $configuration->get('weekdays');
        assert(is_string($operator) && is_array($weekdays));

        $activityWeekday = (int) $activity->getStartDate()->format('N');
        $activityIsOnAConfiguredWeekday = in_array($activityWeekday, array_map(static fn (mixed $weekday): int => (int) $weekday, $weekdays), true);

        return MatchOperator::from($operator)->isSatisfiedBy($activityIsOnAConfiguredWeekday);
    }
}

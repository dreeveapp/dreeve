<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WeekdayCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Weekday', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans('Weekday {operator} {weekdays}', [
            'operator' => MatchOperator::from($configuration->getString('operator'))->trans($translator),
            'weekdays' => implode(', ', array_map(
                fn (mixed $weekday): string => WeekDay::from((int) $weekday)->trans($translator),
                $configuration->getArray('weekdays'),
            )),
        ], 'admin');
    }

    public function getPriority(): int
    {
        return 40;
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
                && null !== WeekDay::tryFrom((int) $weekday);
            if (!$isValidIsoWeekday) {
                throw new InvalidAutomationRule(sprintf('Invalid weekday "%s", expected 1 (Monday) through 7 (Sunday).', is_scalar($weekday) ? (string) $weekday : ''));
            }
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $weekdays = $configuration->getArray('weekdays');

        $activityWeekday = $activity->getStartDate()->getDayOfTheWeek();
        $activityIsOnAConfiguredWeekday = in_array($activityWeekday, array_map('intval', $weekdays), true);

        return MatchOperator::from($operator)->isSatisfiedBy($activityIsOnAConfiguredWeekday);
    }
}

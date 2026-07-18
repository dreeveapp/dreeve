<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TimeOfDayCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Time of day', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s',
            ComparisonOperator::from($configuration->getString('operator'))->transForTimeOfDay($translator),
            $configuration->getString('time'),
        );
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--time-of-day';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => ComparisonOperator::LESS_THAN->value,
            'time' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || null === ComparisonOperator::tryFrom($operator)) {
            throw new InvalidAutomationRule(sprintf('Invalid time of day operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $time = $configuration->get('time');
        if (!is_string($time) || 1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw new InvalidAutomationRule(sprintf('Invalid time "%s", expected HH:MM.', is_scalar($time) ? (string) $time : ''));
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $time = $configuration->getString('time');

        $startDate = $activity->getStartDate();
        $activityMinutes = 60 * (int) $startDate->format('H') + (int) $startDate->format('i');

        $parts = explode(':', $time);
        $configuredMinutes = 60 * (int) $parts[0] + (int) ($parts[1] ?? 0);

        return ComparisonOperator::from($operator)->isSatisfiedBy((float) $activityMinutes, (float) $configuredMinutes);
    }
}

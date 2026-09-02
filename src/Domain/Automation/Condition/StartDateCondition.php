<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class StartDateCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Start date', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s',
            ComparisonOperator::from($configuration->getString('operator'))->transForDate($translator),
            $configuration->getString('date'),
        );
    }

    public function getPriority(): int
    {
        return 45;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--start-date';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => ComparisonOperator::LESS_THAN->value,
            'date' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || null === ComparisonOperator::tryFrom($operator)) {
            throw new InvalidAutomationRule(sprintf('Invalid start date operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $date = $configuration->get('date');
        $isValidDate = is_string($date)
            && 1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        if (!$isValidDate) {
            throw new InvalidAutomationRule(sprintf('Invalid date "%s", expected YYYY-MM-DD.', is_scalar($date) ? (string) $date : ''));
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $date = $configuration->getString('date');

        return ComparisonOperator::from($operator)->isSatisfiedBy(
            actual: (float) $activity->getStartDate()->format('Ymd'),
            expected: (float) str_replace('-', '', $date),
        );
    }
}

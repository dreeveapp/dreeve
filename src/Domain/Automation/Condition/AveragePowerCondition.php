<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AveragePowerCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Average power', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s w',
            ComparisonOperator::from($configuration->getString('operator'))->trans($translator),
            (int) $configuration->getNumber('value'),
        );
    }

    public function getPriority(): int
    {
        return 35;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--average-power';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => ComparisonOperator::GREATER_THAN_OR_EQUAL->value,
            'value' => 0,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || null === ComparisonOperator::tryFrom($operator)) {
            throw new InvalidAutomationRule(sprintf('Invalid average power operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $value = $configuration->get('value');
        if ((!is_int($value) && !is_float($value)) || $value < 0) {
            throw new InvalidAutomationRule('A "value" of at least 0 is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        if (null === $averagePower = $activity->getAveragePower()) {
            // Activities without power data can never satisfy a power condition.
            return false;
        }

        return ComparisonOperator::from($configuration->getString('operator'))->isSatisfiedBy(
            actual: (float) $averagePower,
            expected: (float) $configuration->getNumber('value')
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DistanceCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Distance', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s km',
            ComparisonOperator::from($configuration->getString('operator'))->trans($translator),
            (float) $configuration->getNumber('value'),
        );
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--distance';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => ComparisonOperator::GREATER_THAN_OR_EQUAL->value,
            'value' => 0.0,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || null === ComparisonOperator::tryFrom($operator)) {
            throw new InvalidAutomationRule(sprintf('Invalid distance operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $value = $configuration->get('value');
        if ((!is_int($value) && !is_float($value)) || $value < 0) {
            throw new InvalidAutomationRule('A "value" of at least 0 kilometer is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $value = $configuration->getNumber('value');

        return ComparisonOperator::from($operator)->isSatisfiedBy(
            actual: $activity->getDistance()->toFloat(),
            expected: (float) $value
        );
    }
}

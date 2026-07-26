<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NameCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Activity name', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s "%s"',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            $configuration->getString('value'),
        );
    }

    public function getPriority(): int
    {
        return 5;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--name';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::CONTAINS->value,
            'value' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForText()) {
            throw new InvalidAutomationRule(sprintf('Invalid name operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $value = $configuration->get('value');
        if (!is_string($value) || '' === trim($value)) {
            throw new InvalidAutomationRule('A "value" is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $value = trim($configuration->getString('value'));

        return MatchOperator::from($operator)->isSatisfiedBy(
            '' !== $value && str_contains(mb_strtolower($activity->getName()), mb_strtolower($value))
        );
    }
}

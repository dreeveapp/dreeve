<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SportTypeCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Sport type', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return sprintf(
            '%s %s',
            MatchOperator::from($configuration->getString('operator'))->trans($translator),
            implode(', ', array_map(
                static fn (mixed $sportType): string => SportType::from((string) $sportType)->trans($translator),
                $configuration->getArray('sportTypes'),
            )),
        );
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--sport-type';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => MatchOperator::IS_ONE_OF->value,
            'sportTypes' => [],
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $operator = $configuration->get('operator');
        if (!is_string($operator) || !MatchOperator::tryFrom($operator)?->isForSet()) {
            throw new InvalidAutomationRule(sprintf('Invalid sport type operator "%s".', is_scalar($operator) ? (string) $operator : ''));
        }

        $sportTypes = $configuration->get('sportTypes');
        if (!is_array($sportTypes) || [] === $sportTypes) {
            throw new InvalidAutomationRule('At least one sport type is required.');
        }

        foreach ($sportTypes as $sportType) {
            if (!is_string($sportType) || null === SportType::tryFrom($sportType)) {
                throw new InvalidAutomationRule(sprintf('Invalid sport type "%s".', is_scalar($sportType) ? (string) $sportType : ''));
            }
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->getString('operator');
        $sportTypes = $configuration->getArray('sportTypes');

        return MatchOperator::from($operator)->isSatisfiedBy(
            in_array($activity->getSportType()->value, $sportTypes, true)
        );
    }
}

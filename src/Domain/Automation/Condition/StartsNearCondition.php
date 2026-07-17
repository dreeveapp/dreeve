<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class StartsNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Starts near', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans(
            id: 'Starts {operator} {radius} km of {latitude}, {longitude}',
            parameters: $this->proximityDescriptionParameters($configuration, $translator),
            domain: 'admin',
        );
    }

    public function getPriority(): int
    {
        return 60;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--starts-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $this->coordinateMatches($activity->getStartingCoordinate(), $configuration);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PassesNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private ActivityRouteCoordinates $routeCoordinates,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Passes near', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 80;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--passes-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        $operator = $configuration->get('operator');
        assert(is_string($operator));

        $isWithinOperator = MatchOperator::WITHIN === MatchOperator::from($operator);
        $routeIsEmpty = true;

        foreach ($this->routeCoordinates->all($activity) as $coordinate) {
            $routeIsEmpty = false;

            // "passes near" needs one point within the radius, "outside" needs every point outside it.
            if ($this->coordinateMatches($coordinate, $configuration) === $isWithinOperator) {
                return $isWithinOperator;
            }
        }

        // A route that can never be located: no match for either operator.
        return !$routeIsEmpty && !$isWithinOperator;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EndsNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private ActivityRouteCoordinates $routeCoordinates,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Ends near', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function getTemplateName(): string
    {
        return 'automation-condition--ends-near';
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $this->coordinateMatches($this->routeCoordinates->last($activity), $configuration);
    }
}

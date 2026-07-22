<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Settings\SettingsRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class StartsNearCondition implements Condition
{
    use MatchesCoordinateWithinRadius;

    public function __construct(
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Starts near', domain: 'admin', locale: $locale);
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

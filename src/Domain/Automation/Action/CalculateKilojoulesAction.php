<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CalculateKilojoulesAction implements Action
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Calculate energy (kJ)', domain: 'admin', locale: $locale);
    }

    public function describeValue(TranslatorInterface $translator, RuleConfiguration $configuration): ?string
    {
        return null;
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function getTemplateName(): string
    {
        return 'automation-action--calculate-kilojoules';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::empty();
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $kilojoules = $activity->getKilojoules();
        if (null !== $kilojoules && 0 !== $kilojoules) {
            return $activity;
        }

        $averagePower = $activity->getAveragePower();
        $movingTimeInSeconds = $activity->getMovingTimeInSeconds();

        if (null === $averagePower || 0 === $averagePower || 0 === $movingTimeInSeconds) {
            return $activity;
        }

        return $activity->withKilojoules((int) round($averagePower * $movingTimeInSeconds / 1000));
    }
}

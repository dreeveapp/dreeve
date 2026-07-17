<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Condition\Condition;
use App\Domain\Automation\RuleConfiguration;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DistanceCondition implements Condition
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('Distance', domain: 'admin', locale: $locale);
    }

    public function describe(TranslatorInterface $translator, RuleConfiguration $configuration): string
    {
        return $translator->trans('Distance', domain: 'admin', locale: $locale);
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getTemplateName(): string
    {
        return 'condition/distance.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['minKm' => 0]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $activity->getDistance()->toFloat() >= (int) $configuration->get('minKm');
    }
}

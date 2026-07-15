<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Condition\Condition;
use App\Domain\Automation\RuleConfiguration;

final readonly class DistanceCondition implements Condition
{
    public function getLabel(): string
    {
        return 'Distance';
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

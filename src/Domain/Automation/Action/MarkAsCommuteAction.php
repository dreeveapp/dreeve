<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;

final readonly class MarkAsCommuteAction implements Action
{
    public function getLabel(): string
    {
        return 'Mark as commute';
    }

    public function getTemplateName(): string
    {
        return 'automation-action--mark-as-commute';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'isCommute' => true,
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withCommute((bool) $configuration->get('isCommute'));
    }
}

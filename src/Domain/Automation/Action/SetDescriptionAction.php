<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\RuleConfiguration;

final readonly class SetDescriptionAction implements Action
{
    public function getLabel(): string
    {
        return 'Set description';
    }

    public function getTemplateName(): string
    {
        return 'automation-action--set-description';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'description' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $description = $configuration->get('description');
        assert(is_string($description));

        return $activity->withDescription($description);
    }
}

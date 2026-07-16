<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action;

use App\Domain\Activity\Activity;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;

final readonly class AssignGearAction implements Action
{
    public function getLabel(): string
    {
        return 'Assign gear';
    }

    public function getTemplateName(): string
    {
        return 'automation-action--assign-gear';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'gearId' => '',
        ]);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        $gearId = $configuration->get('gearId');
        if (!is_string($gearId) || '' === trim($gearId)) {
            throw new InvalidAutomationRule('A "gearId" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        $gearId = $configuration->get('gearId');
        assert(is_string($gearId));

        return $activity->withGear(GearId::fromString($gearId));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityName;
use App\Domain\Automation\Action\Action;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;

final readonly class SetNameAction implements Action
{
    public function getLabel(): string
    {
        return 'Set name';
    }

    public function getTemplateName(): string
    {
        return 'action/set-name.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['name' => '']);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        if ('' === trim((string) $configuration->get('name'))) {
            throw new InvalidAutomationRule('A "name" is required.');
        }
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withName(ActivityName::fromString((string) $configuration->get('name')));
    }
}

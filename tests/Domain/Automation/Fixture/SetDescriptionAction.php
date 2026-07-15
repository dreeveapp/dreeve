<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Action;
use App\Domain\Automation\RuleConfiguration;

final readonly class SetDescriptionAction implements Action
{
    public function getLabel(): string
    {
        return 'Set description';
    }

    public function getTemplateName(): string
    {
        return 'action/set-description.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['description' => '']);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
    }

    public function applyTo(Activity $activity, RuleConfiguration $configuration): Activity
    {
        return $activity->withDescription((string) $configuration->get('description'));
    }
}

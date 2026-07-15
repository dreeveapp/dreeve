<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Fixture;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Condition\Condition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;

final readonly class DeviceCondition implements Condition
{
    public function getLabel(): string
    {
        return 'Recorded with device';
    }

    public function getTemplateName(): string
    {
        return 'condition/device.html.twig';
    }

    public function getDefaultConfiguration(): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['deviceName' => '']);
    }

    public function guardValidConfiguration(RuleConfiguration $configuration): void
    {
        if ('' === trim((string) $configuration->get('deviceName'))) {
            throw new InvalidAutomationRule('A "deviceName" is required.');
        }
    }

    public function matches(Activity $activity, RuleConfiguration $configuration): bool
    {
        return $activity->getDeviceName() === $configuration->get('deviceName');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\SetDeviceAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SetDeviceActionTest extends TestCase
{
    private SetDeviceAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['deviceName' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration($this->config('Garmin Edge 1040'));
    }

    public function testGuardThrowsOnEmptyDeviceName(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "deviceName" is required.'));

        $this->action->guardValidConfiguration($this->config('   '));
    }

    public function testApplyToSetsTheConfiguredDevice(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, $this->config('Garmin Edge 1040'));

        $this->assertSame('Garmin Edge 1040', $activity->getDeviceName());
    }

    private function config(string $deviceName): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['deviceName' => $deviceName]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetDeviceAction();
    }
}

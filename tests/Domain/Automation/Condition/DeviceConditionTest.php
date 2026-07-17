<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\DeviceCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class DeviceConditionTest extends TestCase
{
    private DeviceCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'is', 'deviceId' => ''],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('is', 'garmin-edge-130'));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid device operator "nope".'));

        $this->condition->guardValidConfiguration($this->config('nope', 'garmin-edge-130'));
    }

    public function testGuardThrowsOnMissingDeviceId(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "deviceId" is required.'));

        $this->condition->guardValidConfiguration($this->config('is', '  '));
    }

    public function testMatchesNormalisingTypedAndPickedNames(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDeviceName('Garmin Edge 130')->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('is', 'garmin-edge-130')));
    }

    public function testDoesNotMatchADifferentDevice(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo Elemnt')->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('is', 'garmin-edge-130')));
    }

    public function testDoesNotMatchWhenActivityHasNoDevice(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('is', 'garmin-edge-130')));
    }

    public function testIsNotOperatorInvertsTheMatch(): void
    {
        $garmin = ActivityBuilder::fromDefaults()->withDeviceName('Garmin Edge 130')->build();
        $wahoo = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo Elemnt')->build();
        $noDevice = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($garmin, $this->config('isNot', 'garmin-edge-130')));
        $this->assertTrue($this->condition->matches($wahoo, $this->config('isNot', 'garmin-edge-130')));
        $this->assertTrue($this->condition->matches($noDevice, $this->config('isNot', 'garmin-edge-130')));
    }

    private function config(string $operator, string $deviceId): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'deviceId' => $deviceId]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new DeviceCondition($this->createStub(RecordingDeviceRepository::class));
    }
}

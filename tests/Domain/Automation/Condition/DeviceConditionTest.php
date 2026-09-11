<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\DeviceCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeviceConditionTest extends TestCase
{
    private DeviceCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'is', 'deviceName' => ''],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'is', 'deviceName' => 'Garmin Edge 130']));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, string $deviceName, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'deviceName' => $deviceName]));
    }

    #[DataProvider('provideMatchingDeviceNames')]
    public function testMatchesNormalisingTypedAndPickedNames(string $deviceName): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDeviceName('Garmin Edge 130')->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceName' => $deviceName])));
    }

    public function testDoesNotMatchADifferentDevice(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo Elemnt')->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceName' => 'Garmin Edge 130'])));
    }

    public function testDoesNotMatchWhenActivityHasNoDevice(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'is', 'deviceName' => 'Garmin Edge 130'])));
    }

    public function testIsNotOperatorInvertsTheMatch(): void
    {
        $garmin = ActivityBuilder::fromDefaults()->withDeviceName('Garmin Edge 130')->build();
        $wahoo = ActivityBuilder::fromDefaults()->withDeviceName('Wahoo Elemnt')->build();
        $noDevice = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($garmin, RuleConfiguration::fromConfig(['operator' => 'isNot', 'deviceName' => 'Garmin Edge 130'])));
        $this->assertTrue($this->condition->matches($wahoo, RuleConfiguration::fromConfig(['operator' => 'isNot', 'deviceName' => 'Garmin Edge 130'])));
        $this->assertTrue($this->condition->matches($noDevice, RuleConfiguration::fromConfig(['operator' => 'isNot', 'deviceName' => 'Garmin Edge 130'])));
    }

    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', 'Garmin Edge 130', 'Invalid device operator "nope".'];
        yield 'missing device name' => ['is', '  ', 'A "deviceName" is required.'];
    }

    public static function provideMatchingDeviceNames(): iterable
    {
        yield 'exact name' => ['Garmin Edge 130'];
        yield 'different casing' => ['garmin edge 130'];
        yield 'legacy device id' => ['garmin-edge-130'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new DeviceCondition();
    }
}

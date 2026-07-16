<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    private Conditions $conditions;

    public function testHas(): void
    {
        $this->assertTrue($this->conditions->has(ConditionType::DEVICE));
        $this->assertTrue($this->conditions->has(ConditionType::DISTANCE));
        $this->assertFalse($this->conditions->has(ConditionType::SPORT_TYPE));
    }

    public function testGetReturnsTheServiceKeyedByItsType(): void
    {
        $this->assertInstanceOf(DeviceCondition::class, $this->conditions->get(ConditionType::DEVICE));
        $this->assertInstanceOf(DistanceCondition::class, $this->conditions->get(ConditionType::DISTANCE));
    }

    public function testGetThrowsForUnregisteredType(): void
    {
        $this->expectExceptionObject(
            new InvalidAutomationRule('No condition registered for type "sportType".')
        );

        $this->conditions->get(ConditionType::SPORT_TYPE);
    }

    public function testAllIsSortedByPriority(): void
    {
        $this->assertSame(
            [ConditionType::DEVICE->value, ConditionType::DISTANCE->value],
            array_keys($this->conditions->all())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->conditions = new Conditions([
            new DeviceCondition(),
            new DistanceCondition(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    private Conditions $conditions;

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

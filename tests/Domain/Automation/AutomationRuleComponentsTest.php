<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\Action\Actions;
use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\AutomationRuleComponents;
use App\Domain\Automation\Condition\Conditions;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\InvalidAutomationRule;
use App\Infrastructure\Serialization\Json;
use App\Tests\Domain\Automation\Fixture\DeviceCondition;
use App\Tests\Domain\Automation\Fixture\DistanceCondition;
use App\Tests\Domain\Automation\Fixture\SetNameAction;
use PHPUnit\Framework\TestCase;

class AutomationRuleComponentsTest extends TestCase
{
    private AutomationRuleComponents $components;

    public function testBuildConditionsCoercesConfigAndDropsUnknownKeys(): void
    {
        $conditions = $this->components->buildConditions([
            ['type' => ConditionType::DEVICE, 'config' => ['deviceName' => 'Garmin', 'junk' => 'dropped']],
            ['type' => ConditionType::DISTANCE, 'config' => ['minKm' => '15']],
        ]);

        $this->assertSame(
            '[{"type":"device","config":{"deviceName":"Garmin"}},{"type":"distance","config":{"minKm":15}}]',
            Json::encode($conditions)
        );
    }

    public function testBuildActionsCoercesConfig(): void
    {
        $actions = $this->components->buildActions([
            ['type' => ActionType::SET_NAME, 'config' => ['name' => 'Commute']],
        ]);

        $this->assertSame(
            '[{"type":"setName","config":{"name":"Commute"}}]',
            Json::encode($actions)
        );
    }

    public function testBuildConditionsThrowsWhenGuardFails(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "deviceName" is required.'));

        $this->components->buildConditions([
            ['type' => ConditionType::DEVICE, 'config' => ['deviceName' => '']],
        ]);
    }

    public function testBuildConditionsThrowsForUnregisteredType(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('No condition registered for type "sportType".'));

        $this->components->buildConditions([
            ['type' => ConditionType::SPORT_TYPE, 'config' => []],
        ]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->components = new AutomationRuleComponents(
            new Conditions([new DeviceCondition(), new DistanceCondition()]),
            new Actions([new SetNameAction()]),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredActions;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredCondition;
use App\Domain\Automation\Condition\ConfiguredCondition\ConfiguredConditions;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class AutomationRuleTest extends TestCase
{
    public function testCreateAndGetters(): void
    {
        $conditions = ConfiguredConditions::fromArray([
            new ConfiguredCondition(ConditionType::DEVICE, RuleConfiguration::fromConfig(['deviceName' => 'Garmin'])),
        ]);
        $actions = ConfiguredActions::fromArray([
            new ConfiguredAction(ActionType::SET_NAME, RuleConfiguration::fromConfig(['name' => 'Commute'])),
        ]);

        $rule = AutomationRuleBuilder::fromDefaults()
            ->withAutomationRuleId(AutomationRuleId::fromUnprefixed('42'))
            ->withLabel('Tag commutes')
            ->withIsEnabled(false)
            ->withSortOrder(3)
            ->withConditions($conditions)
            ->withActions($actions)
            ->withCreatedOn(SerializableDateTime::fromString('2023-10-17 16:15:04'))
            ->build();

        $this->assertSame('automationRule-42', (string) $rule->getId());
        $this->assertSame('Tag commutes', $rule->getLabel());
        $this->assertFalse($rule->isEnabled());
        $this->assertSame(3, $rule->getSortOrder());
        $this->assertSame($conditions, $rule->getConditions());
        $this->assertSame($actions, $rule->getActions());
        $this->assertEquals(SerializableDateTime::fromString('2023-10-17 16:15:04'), $rule->getCreatedOn());
    }

    public function testWithMutatorsReturnNewInstanceLeavingOriginalUntouched(): void
    {
        $rule = AutomationRuleBuilder::fromDefaults()
            ->withLabel('Original')
            ->withIsEnabled(true)
            ->withSortOrder(1)
            ->build();

        $updated = $rule
            ->withLabel('Updated')
            ->withIsEnabled(false)
            ->withSortOrder(9);

        $this->assertSame('Original', $rule->getLabel());
        $this->assertTrue($rule->isEnabled());
        $this->assertSame(1, $rule->getSortOrder());

        $this->assertSame('Updated', $updated->getLabel());
        $this->assertFalse($updated->isEnabled());
        $this->assertSame(9, $updated->getSortOrder());
        $this->assertSame((string) $rule->getId(), (string) $updated->getId());
    }
}

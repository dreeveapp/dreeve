<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\UpdateAutomationRule;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\UpdateAutomationRule\UpdateAutomationRule;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class UpdateAutomationRuleTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-42',
            'label' => 'Tag commutes',
            'enabled' => false,
            'stopProcessing' => false,
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ]);

        $this->assertSame('automationRule-42', (string) $command->getAutomationRuleId());
        $this->assertSame('Tag commutes', $command->getLabel());
        $this->assertFalse($command->isEnabled());
        $this->assertFalse($command->stopProcessing());
        $this->assertSame(
            [['type' => ConditionType::DEVICE, 'config' => ['deviceName' => 'Garmin']]],
            $command->getConditions()
        );
        $this->assertSame(
            [['type' => ActionType::SET_NAME, 'config' => ['name' => 'Commute']]],
            $command->getActions()
        );
    }

    public function testAutomationRuleIdIsTrimmed(): void
    {
        $command = UpdateAutomationRule::fromPayload([
            'automationRuleId' => '  automationRule-42  ',
            'label' => 'Tag commutes',
            'conditions' => [['type' => 'device']],
            'actions' => [['type' => 'setName']],
        ]);

        $this->assertSame('automationRule-42', (string) $command->getAutomationRuleId());
    }

    public function testStopProcessingDefaultsToTrue(): void
    {
        $command = UpdateAutomationRule::fromPayload([
            'automationRuleId' => 'automationRule-42',
            'label' => 'Tag commutes',
            'conditions' => [['type' => 'device']],
            'actions' => [['type' => 'setName']],
        ]);

        $this->assertTrue($command->stopProcessing());
    }

    public function testThrowsOnMissingAutomationRuleId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "automationRuleId" is required.'));

        UpdateAutomationRule::fromPayload([
            'label' => 'Tag commutes',
            'conditions' => [['type' => 'device']],
            'actions' => [['type' => 'setName']],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\AddAutomationRule;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\AddAutomationRule\AddAutomationRule;
use App\Domain\Automation\Condition\ConditionType;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class AddAutomationRuleTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddAutomationRule::fromPayload([
            'label' => 'Tag commutes',
            'enabled' => true,
            'conditions' => [
                ['type' => 'device', 'config' => ['deviceName' => 'Garmin']],
            ],
            'actions' => [
                ['type' => 'setName', 'config' => ['name' => 'Commute']],
            ],
        ]);

        $this->assertSame('Tag commutes', $command->getLabel());
        $this->assertTrue($command->isEnabled());
        $this->assertSame(
            [['type' => ConditionType::DEVICE, 'config' => ['deviceName' => 'Garmin']]],
            $command->getConditions()
        );
        $this->assertSame(
            [['type' => ActionType::SET_NAME, 'config' => ['name' => 'Commute']]],
            $command->getActions()
        );
    }

    public function testEnabledDefaultsToTrue(): void
    {
        $command = AddAutomationRule::fromPayload($this->validPayloadWithout('enabled'));

        $this->assertTrue($command->isEnabled());
    }

    public function testEnabledCanBeDisabled(): void
    {
        $command = AddAutomationRule::fromPayload(['enabled' => false] + $this->validPayload());

        $this->assertFalse($command->isEnabled());
    }

    public function testLabelIsTrimmed(): void
    {
        $command = AddAutomationRule::fromPayload(['label' => '  Trimmed  '] + $this->validPayload());

        $this->assertSame('Trimmed', $command->getLabel());
    }

    public function testConfigDefaultsToEmptyArray(): void
    {
        $command = AddAutomationRule::fromPayload([
            'label' => 'No config',
            'conditions' => [['type' => 'device']],
            'actions' => [['type' => 'setName']],
        ]);

        $this->assertSame([['type' => ConditionType::DEVICE, 'config' => []]], $command->getConditions());
    }

    public function testThrowsOnMissingLabel(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.'));

        AddAutomationRule::fromPayload($this->validPayloadWithout('label'));
    }

    public function testThrowsOnEmptyConditions(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('At least one condition is required.'));

        AddAutomationRule::fromPayload(['conditions' => []] + $this->validPayload());
    }

    public function testThrowsOnEmptyActions(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('At least one action is required.'));

        AddAutomationRule::fromPayload(['actions' => []] + $this->validPayload());
    }

    public function testThrowsOnInvalidConditionType(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Invalid condition type "nope".'));

        AddAutomationRule::fromPayload(['conditions' => [['type' => 'nope']]] + $this->validPayload());
    }

    public function testThrowsOnInvalidActionType(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Invalid action type "nope".'));

        AddAutomationRule::fromPayload(['actions' => [['type' => 'nope']]] + $this->validPayload());
    }

    public function testThrowsWhenComponentHasNoType(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each component requires a non-empty "type".'));

        AddAutomationRule::fromPayload(['conditions' => [['config' => []]]] + $this->validPayload());
    }

    public function testThrowsWhenConfigIsNotAnObject(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A component "config" must be an object.'));

        AddAutomationRule::fromPayload(['conditions' => [['type' => 'device', 'config' => 'nope']]] + $this->validPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'label' => 'Tag commutes',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayloadWithout(string $key): array
    {
        $payload = $this->validPayload();
        unset($payload[$key]);

        return $payload;
    }
}

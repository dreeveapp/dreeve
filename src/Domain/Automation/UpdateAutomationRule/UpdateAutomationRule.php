<?php

declare(strict_types=1);

namespace App\Domain\Automation\UpdateAutomationRule;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\ParsesAutomationRulePayload;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class UpdateAutomationRule extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    use ParsesAutomationRulePayload;

    /**
     * @param list<array{type: ConditionType, config: array<string, mixed>}> $conditions
     * @param list<array{type: ActionType, config: array<string, mixed>}>    $actions
     */
    private function __construct(
        private AutomationRuleId $automationRuleId,
        private string $label,
        private bool $isEnabled,
        private array $conditions,
        private array $actions,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['automationRuleId']) || !is_string($payload['automationRuleId']) || '' === trim($payload['automationRuleId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "automationRuleId" is required.');
        }

        return new self(
            automationRuleId: AutomationRuleId::fromString(trim($payload['automationRuleId'])),
            label: self::parseLabel($payload),
            isEnabled: self::parseIsEnabled($payload),
            conditions: self::parseConditions($payload),
            actions: self::parseActions($payload),
        );
    }

    public function getAutomationRuleId(): AutomationRuleId
    {
        return $this->automationRuleId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @return list<array{type: ConditionType, config: array<string, mixed>}>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return list<array{type: ActionType, config: array<string, mixed>}>
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}

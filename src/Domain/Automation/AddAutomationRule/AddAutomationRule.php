<?php

declare(strict_types=1);

namespace App\Domain\Automation\AddAutomationRule;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Condition\ConditionType;
use App\Domain\Automation\ParsesAutomationRulePayload;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class AddAutomationRule extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;
    use ParsesAutomationRulePayload;

    /**
     * @param list<array{type: ConditionType, config: array<string, mixed>}> $conditions
     * @param list<array{type: ActionType, config: array<string, mixed>}>    $actions
     */
    private function __construct(
        private string $label,
        private bool $isEnabled,
        private bool $stopProcessing,
        private array $conditions,
        private array $actions,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            label: self::parseLabel($payload),
            isEnabled: self::parseIsEnabled($payload),
            stopProcessing: self::parseStopProcessing($payload),
            conditions: self::parseConditions($payload),
            actions: self::parseActions($payload),
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function stopProcessing(): bool
    {
        return $this->stopProcessing;
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

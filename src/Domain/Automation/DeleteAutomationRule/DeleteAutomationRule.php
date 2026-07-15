<?php

declare(strict_types=1);

namespace App\Domain\Automation\DeleteAutomationRule;

use App\Domain\Automation\AutomationRuleId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class DeleteAutomationRule extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private AutomationRuleId $automationRuleId,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        if (!isset($payload['automationRuleId']) || !is_string($payload['automationRuleId']) || '' === trim($payload['automationRuleId'])) {
            throw CouldNotDeserializeCommand::invalidPayload('An "automationRuleId" is required.');
        }

        return new self(
            automationRuleId: AutomationRuleId::fromString(trim($payload['automationRuleId'])),
        );
    }

    public function getAutomationRuleId(): AutomationRuleId
    {
        return $this->automationRuleId;
    }
}

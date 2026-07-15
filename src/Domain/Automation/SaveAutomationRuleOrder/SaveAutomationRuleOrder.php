<?php

declare(strict_types=1);

namespace App\Domain\Automation\SaveAutomationRuleOrder;

use App\Domain\Automation\AutomationRuleId;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\SuppressesFlashMessage;

#[SuppressesFlashMessage]
final readonly class SaveAutomationRuleOrder extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param list<AutomationRuleId> $orderedIds
     */
    private function __construct(
        private array $orderedIds,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $order = $payload['order'] ?? null;
        if (!is_array($order) || [] === $order || !array_is_list($order)) {
            throw CouldNotDeserializeCommand::invalidPayload('A non-empty "order" list is required.');
        }

        $orderedIds = [];
        foreach ($order as $id) {
            if (!is_string($id) || '' === trim($id)) {
                throw CouldNotDeserializeCommand::invalidPayload('Each "order" entry must be a non-empty string.');
            }
            $orderedIds[] = AutomationRuleId::fromString(trim($id));
        }

        return new self($orderedIds);
    }

    /**
     * @return list<AutomationRuleId>
     */
    public function getOrderedIds(): array
    {
        return $this->orderedIds;
    }
}

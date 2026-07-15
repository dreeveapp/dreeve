<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\SaveAutomationRuleOrder;

use App\Domain\Automation\SaveAutomationRuleOrder\SaveAutomationRuleOrder;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class SaveAutomationRuleOrderTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = SaveAutomationRuleOrder::fromPayload([
            'order' => ['automationRule-2', 'automationRule-1', 'automationRule-3'],
        ]);

        $this->assertSame(
            ['automationRule-2', 'automationRule-1', 'automationRule-3'],
            array_map(static fn ($id): string => (string) $id, $command->getOrderedIds())
        );
    }

    public function testEntriesAreTrimmed(): void
    {
        $command = SaveAutomationRuleOrder::fromPayload([
            'order' => ['  automationRule-2  '],
        ]);

        $this->assertSame('automationRule-2', (string) $command->getOrderedIds()[0]);
    }

    public function testThrowsWhenOrderIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "order" list is required.'));

        SaveAutomationRuleOrder::fromPayload([]);
    }

    public function testThrowsWhenOrderIsEmpty(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "order" list is required.'));

        SaveAutomationRuleOrder::fromPayload(['order' => []]);
    }

    public function testThrowsWhenOrderIsNotAList(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "order" list is required.'));

        SaveAutomationRuleOrder::fromPayload(['order' => ['a' => 'automationRule-1']]);
    }

    public function testThrowsWhenAnEntryIsNotANonEmptyString(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each "order" entry must be a non-empty string.'));

        SaveAutomationRuleOrder::fromPayload(['order' => ['automationRule-1', '  ']]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\SaveAutomationRuleOrder;

use App\Domain\Automation\SaveAutomationRuleOrder\SaveAutomationRuleOrder;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        SaveAutomationRuleOrder::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing order' => [
            [],
            'A non-empty "order" list is required.',
        ];

        yield 'empty order' => [
            ['order' => []],
            'A non-empty "order" list is required.',
        ];

        yield 'order that is not a list' => [
            ['order' => ['a' => 'automationRule-1']],
            'A non-empty "order" list is required.',
        ];

        yield 'entry that is not a non-empty string' => [
            ['order' => ['automationRule-1', '  ']],
            'Each "order" entry must be a non-empty string.',
        ];
    }
}

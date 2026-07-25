<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\SaveDashboardLayout;

use App\Domain\Dashboard\SaveDashboardLayout\SaveDashboardLayout;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaveDashboardLayoutTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = SaveDashboardLayout::fromPayload([
            'layout' => [
                ['id' => '  dashboardWidget-a  ', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'width' => 100],
            ],
        ]);

        $this->assertSame([
            ['id' => 'dashboardWidget-a', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'width' => 100],
        ], $command->getOrderedWidgets());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        SaveDashboardLayout::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing layout' => [
            [],
            'A non-empty "layout" list is required.',
        ];

        yield 'empty layout' => [
            ['layout' => []],
            'A non-empty "layout" list is required.',
        ];

        yield 'layout is not a list' => [
            ['layout' => ['a' => ['id' => 'dashboardWidget-a', 'width' => 33]]],
            'A non-empty "layout" list is required.',
        ];

        yield 'missing id' => [
            ['layout' => [['width' => 33]]],
            'Each layout item requires a non-empty "id".',
        ];

        yield 'empty id' => [
            ['layout' => [['id' => '  ', 'width' => 33]]],
            'Each layout item requires a non-empty "id".',
        ];

        yield 'missing width' => [
            ['layout' => [['id' => 'dashboardWidget-a']]],
            'Each layout item requires a "width" that is one of [33, 50, 66, 100].',
        ];

        yield 'out of range width' => [
            ['layout' => [['id' => 'dashboardWidget-a', 'width' => 42]]],
            'Each layout item requires a "width" that is one of [33, 50, 66, 100].',
        ];

        yield 'width is not an integer' => [
            ['layout' => [['id' => 'dashboardWidget-a', 'width' => '33']]],
            'Each layout item requires a "width" that is one of [33, 50, 66, 100].',
        ];
    }
}

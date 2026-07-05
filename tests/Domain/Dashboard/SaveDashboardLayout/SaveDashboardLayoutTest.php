<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\SaveDashboardLayout;

use App\Domain\Dashboard\SaveDashboardLayout\SaveDashboardLayout;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
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

    public function testFromPayloadThrowsOnMissingLayout(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "layout" list is required.'));

        SaveDashboardLayout::fromPayload([]);
    }

    public function testFromPayloadThrowsOnEmptyLayout(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "layout" list is required.'));

        SaveDashboardLayout::fromPayload(['layout' => []]);
    }

    public function testFromPayloadThrowsWhenLayoutIsNotAList(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "layout" list is required.'));

        SaveDashboardLayout::fromPayload(['layout' => ['a' => ['id' => 'dashboardWidget-a', 'width' => 33]]]);
    }

    public function testFromPayloadThrowsOnMissingId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each layout item requires a non-empty "id".'));

        SaveDashboardLayout::fromPayload(['layout' => [['width' => 33]]]);
    }

    public function testFromPayloadThrowsOnEmptyId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each layout item requires a non-empty "id".'));

        SaveDashboardLayout::fromPayload(['layout' => [['id' => '  ', 'width' => 33]]]);
    }

    public function testFromPayloadThrowsOnMissingWidth(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each layout item requires a "width" that is one of [33, 50, 66, 100].'));

        SaveDashboardLayout::fromPayload(['layout' => [['id' => 'dashboardWidget-a']]]);
    }

    public function testFromPayloadThrowsOnOutOfRangeWidth(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each layout item requires a "width" that is one of [33, 50, 66, 100].'));

        SaveDashboardLayout::fromPayload(['layout' => [['id' => 'dashboardWidget-a', 'width' => 42]]]);
    }

    public function testFromPayloadThrowsWhenWidthIsNotAnInteger(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each layout item requires a "width" that is one of [33, 50, 66, 100].'));

        SaveDashboardLayout::fromPayload(['layout' => [['id' => 'dashboardWidget-a', 'width' => '33']]]);
    }
}

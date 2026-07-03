<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\ConfigureWidget;

use App\Domain\Dashboard\ConfigureWidget\ConfigureWidget;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class ConfigureWidgetTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = ConfigureWidget::fromPayload([
            'dashboardWidgetId' => '  dashboardWidget-streaks  ',
            'config' => ['subtitle' => 'Hello'],
        ]);

        $this->assertSame('dashboardWidget-streaks', (string) $command->getDashboardWidgetId());
        $this->assertSame(['subtitle' => 'Hello'], $command->getConfiguration());
    }

    public function testFromPayloadDefaultsToEmptyConfig(): void
    {
        $command = ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-streaks',
        ]);

        $this->assertSame([], $command->getConfiguration());
    }

    public function testFromPayloadThrowsOnMissingDashboardWidgetId(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "dashboardWidgetId" is required.'));

        ConfigureWidget::fromPayload(['config' => []]);
    }

    public function testFromPayloadThrowsOnInvalidConfig(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('The "config" must be an object.'));

        ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-streaks',
            'config' => 'not-an-array',
        ]);
    }
}

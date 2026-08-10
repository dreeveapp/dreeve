<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Dashboard\Widget\ZwiftStatsWidget;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class ZwiftStatsWidgetTest extends ContainerTestCase
{
    private ZwiftStatsWidget $widget;

    public function testItShouldRenderNull(): void
    {
        $this->assertNull($this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-12-02'),
            configuration: WidgetConfiguration::empty()
        ));
    }

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(ZwiftStatsWidget::class);
    }
}

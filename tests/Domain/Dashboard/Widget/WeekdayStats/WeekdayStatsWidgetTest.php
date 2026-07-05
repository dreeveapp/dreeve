<?php

namespace App\Tests\Domain\Dashboard\Widget\WeekdayStats;

use App\Domain\Dashboard\Widget\WeekdayStats\WeekdayStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;

class WeekdayStatsWidgetTest extends ContainerTestCase
{
    private WeekdayStatsWidget $widget;

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(WeekdayStatsWidget::class);
    }
}

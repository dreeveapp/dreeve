<?php

namespace App\Tests\Domain\Dashboard\Widget\DaytimeStats;

use App\Domain\Dashboard\Widget\DaytimeStats\DayTimeStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;

class DayTimeStatsWidgetTest extends ContainerTestCase
{
    private DayTimeStatsWidget $widget;

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(DayTimeStatsWidget::class);
    }
}

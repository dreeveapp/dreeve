<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\HeartRateZonesWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;

class HeartRateZonesWidgetTest extends ContainerTestCase
{
    private HeartRateZonesWidget $widget;

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(HeartRateZonesWidget::class);
    }
}

<?php

namespace App\Tests\Domain\Dashboard\Widget\DistanceBreakdown;

use App\Domain\Dashboard\Widget\DistanceBreakdown\DistanceBreakdownWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;

class DistanceBreakdownWidgetTest extends ContainerTestCase
{
    private DistanceBreakdownWidget $widget;

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(DistanceBreakdownWidget::class);
    }
}

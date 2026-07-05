<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\IntroTextWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Tests\ContainerTestCase;

class IntroTextWidgetTest extends ContainerTestCase
{
    private IntroTextWidget $widget;

    public function testGuardValidConfigurationItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(IntroTextWidget::class);
    }
}

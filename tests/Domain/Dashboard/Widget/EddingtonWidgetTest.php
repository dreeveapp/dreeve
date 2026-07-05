<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\Widget\EddingtonWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class EddingtonWidgetTest extends ContainerTestCase
{
    private EddingtonWidget $widget;

    public function testRenderWhenNoEddingtons(): void
    {
        $this->assertNull($this->widget->render(
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

        $this->widget = $this->getContainer()->get(EddingtonWidget::class);
    }
}

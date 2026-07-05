<?php

namespace App\Tests\Domain\Dashboard\Widget\AthleteProfile;

use App\Domain\Dashboard\Widget\AthleteProfile\AthleteProfileWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class AthleteProfileWidgetTest extends ContainerTestCase
{
    private AthleteProfileWidget $widget;

    public function testRenderWhenEmptyChartData(): void
    {
        $this->assertNull(
            $this->widget->render(
                SerializableDateTime::fromString('2026-01-09'),
                WidgetConfiguration::empty()
            )
        );
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

        $this->widget = $this->getContainer()->get(AthleteProfileWidget::class);
    }
}

<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\AthleteWeightHistoryWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class AthleteWeightHistoryWidgetTest extends ContainerTestCase
{
    private AthleteWeightHistoryWidget $widget;

    public function testRenderWhenNoWeights(): void
    {
        $this->getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::ATHLETE_WEIGHT, []);

        $this->assertNull(
            $this->widget->render(
                DashboardWidgetId::fromUnprefixed('test'),
                SerializableDateTime::fromString('2026-01-09'),
                WidgetConfiguration::empty()
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(AthleteWeightHistoryWidget::class);
    }
}

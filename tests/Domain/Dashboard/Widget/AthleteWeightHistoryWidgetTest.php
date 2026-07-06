<?php

namespace App\Tests\Domain\Dashboard\Widget;

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
        // Remove the weight history from the general settings.
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsGroup::GENERAL, [
            'athlete' => [
                'birthday' => '1989-08-14',
                'firstName' => 'Robin',
                'lastName' => 'Ingelbrecht',
                'maxHeartRateFormula' => 'fox',
            ],
        ]);

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

        $this->widget = $this->getContainer()->get(AthleteWeightHistoryWidget::class);
    }
}

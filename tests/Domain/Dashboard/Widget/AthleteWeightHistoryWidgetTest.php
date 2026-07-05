<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Dashboard\Widget\AthleteWeightHistoryWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AthleteWeightHistoryWidgetTest extends ContainerTestCase
{
    private AthleteWeightHistoryWidget $widget;

    public function testRenderWhenNoWeights(): void
    {
        $widget = new AthleteWeightHistoryWidget(
            AthleteWeightHistory::fromArray([], UnitSystem::METRIC),
            UnitSystem::METRIC,
            $this->getContainer()->get(Environment::class),
            $this->getContainer()->get(TranslatorInterface::class),
        );

        $this->assertNull(
            $widget->render(
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

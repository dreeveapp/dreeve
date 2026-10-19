<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\EddingtonWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class EddingtonWidgetTest extends ContainerTestCase
{
    private EddingtonWidget $widget;

    public function testRenderWhenNoEddingtons(): void
    {
        $this->assertNull($this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-12-02'),
            configuration: WidgetConfiguration::empty()
        ));
    }

    public function testRenderWhenTheLongestDayEqualsTheEddingtonNumber(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::RIDE)
                ->withDistance(Kilometer::from(1.4))
                ->build(),
            [],
        ));

        $this->assertMatchesRegularExpression(
            '/font-extrabold">\s*2\s*<\/div>\s*<div class="text-sm">days needed for next Eddington/',
            (string) $this->widget->render(
                dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
                now: SerializableDateTime::fromString('2025-12-02'),
                configuration: WidgetConfiguration::empty()
            )
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(EddingtonWidget::class);
    }
}

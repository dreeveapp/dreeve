<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetric;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\PeakPowerOutputsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class PeakPowerOutputsWidgetTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private PeakPowerOutputsWidget $widget;

    public function testItRendersForAnActivityPredatingTheAthleteWeightHistory(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType(SportType::RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2019-01-01 10:00:00'))
                ->build(),
            [],
        ));
        $this->getContainer()->get(ActivityStreamMetricRepository::class)->add(ActivityStreamMetric::create(
            activityId: $activityId,
            streamType: StreamType::WATTS,
            metricType: ActivityStreamMetricType::BEST_AVERAGES,
            data: [5 => 900, 60 => 500, 3600 => 250],
        ));

        $this->assertMatchesHtmlSnapshot((string) $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-12-02'),
            configuration: WidgetConfiguration::empty()
        ));
    }

    public function testItShouldRenderNull(): void
    {
        $this->assertNull($this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
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

        $this->widget = $this->getContainer()->get(PeakPowerOutputsWidget::class);
    }
}

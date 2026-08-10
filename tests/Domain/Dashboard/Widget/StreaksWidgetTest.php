<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\StreaksWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class StreaksWidgetTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StreaksWidget $widget;

    public function testRenderWithSportTypesToInclude(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-03 00:00:00'))
                ->withSportType(SportType::ALPINE_SKI)
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 00:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));
        $configuration = WidgetConfiguration::empty()->add('sportTypesToInclude', ['Ride']);

        $this->assertMatchesHtmlSnapshot(
            $this->widget->render(
                dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
                now: SerializableDateTime::fromString('2025-12-31'),
                configuration: $configuration
            )
        );
    }

    public function testGuardValidConfigurationWhenSportTypesIsNotArray(): void
    {
        $configuration = WidgetConfiguration::empty()->add('sportTypesToInclude', 'lol');

        $this->expectExceptionObject(new InvalidDashboardLayout('Configuration item "sportTypesToInclude" must be an array for StreaksWidget.'));
        $this->widget->guardValidConfiguration($configuration);
    }

    public function testGuardValidConfigurationWhenSportTypesIsNotValid(): void
    {
        $configuration = WidgetConfiguration::empty()->add('sportTypesToInclude', ['lol']);

        $this->expectExceptionObject(new InvalidDashboardLayout('"lol" is not a valid sport type'));
        $this->widget->guardValidConfiguration($configuration);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(StreaksWidget::class);
    }
}

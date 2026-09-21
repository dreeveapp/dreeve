<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\MostRecentActivityCardsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class MostRecentActivityCardsWidgetTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private MostRecentActivityCardsWidget $widget;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', [])
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderWithMultipleActivities(): void
    {
        $this->provideFullTestSet();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 3)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', [])
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderForAnActivityWithoutAMap(): void
    {
        $this->provideFullTestSet();
        $this->addActivityWithoutARoute();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', [])
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderWithOnlyActivitiesWithAMapItShouldSkipActivitiesWithoutARoute(): void
    {
        $this->provideFullTestSet();
        $this->addActivityWithoutARoute();

        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', true)
                ->add('sportTypesToInclude', [])
        );

        $this->assertStringNotContainsString('Indoor Ride', (string) $render);
        $this->assertStringContainsString('data-leaflet', (string) $render);
    }

    public function testRenderWithSportTypesToInclude(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('theRun'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-03 00:00:00'))
                ->withSportType(SportType::RUN)
                ->withName('Morning Run')
                ->build(),
            ['raw' => 'data']
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('theRide'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 00:00:00'))
                ->withSportType(SportType::RIDE)
                ->withName('Evening Ride')
                ->build(),
            ['raw' => 'data']
        ));

        $render = (string) $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', ['Ride'])
        );

        $this->assertStringContainsString('Evening Ride', $render);
        $this->assertStringNotContainsString('Morning Run', $render);
    }

    public function testRenderWhenNoActivityMatchesTheSportTypesToInclude(): void
    {
        $this->provideFullTestSet();

        $this->assertNull($this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', ['AlpineSki'])
        ));
    }

    private function addActivityWithoutARoute(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('withoutARoute'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 10:00:00'))
                ->withName('Indoor Ride')
                ->withPolyline(null)
                ->build(),
            ['raw' => 'data']
        ));
    }

    public function testRenderWhenThereAreNoActivities(): void
    {
        $render = $this->widget->render(
            dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
                ->add('numberOfActivitiesToDisplay', 1)
                ->add('onlyShowActivitiesWithAMap', false)
                ->add('sportTypesToInclude', [])
        );
        $this->assertNull($render);
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "numberOfActivitiesToDisplay" key' => [WidgetConfiguration::empty(), 'Configuration item "numberOfActivitiesToDisplay" is required for MostRecentActivityCardsWidget.'];
        yield 'invalid "numberOfActivitiesToDisplay" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 'lol'), 'Configuration item "numberOfActivitiesToDisplay" must be an integer.'];
        yield 'too low "numberOfActivitiesToDisplay" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 0), 'Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.'];
        yield 'too high "numberOfActivitiesToDisplay" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 4), 'Configuration item "numberOfActivitiesToDisplay" must be set to a value of 3 or lower.'];
        yield 'missing "onlyShowActivitiesWithAMap" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 1), 'Configuration item "onlyShowActivitiesWithAMap" is required for MostRecentActivityCardsWidget.'];
        yield 'invalid "onlyShowActivitiesWithAMap" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 1)->add('onlyShowActivitiesWithAMap', 'lol'), 'Configuration item "onlyShowActivitiesWithAMap" must be a boolean.'];
        yield 'invalid "sportTypesToInclude" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 1)->add('onlyShowActivitiesWithAMap', false)->add('sportTypesToInclude', 'lol'), 'Configuration item "sportTypesToInclude" must be an array for MostRecentActivityCardsWidget.'];
        yield 'unknown sport type in "sportTypesToInclude" key' => [WidgetConfiguration::empty()->add('numberOfActivitiesToDisplay', 1)->add('onlyShowActivitiesWithAMap', false)->add('sportTypesToInclude', ['lol']), '"lol" is not a valid sport type'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(MostRecentActivityCardsWidget::class);
    }
}

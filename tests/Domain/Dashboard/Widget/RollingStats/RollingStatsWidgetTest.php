<?php

namespace App\Tests\Domain\Dashboard\Widget\RollingStats;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\RollingStats\RollingStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class RollingStatsWidgetTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private RollingStatsWidget $widget;

    public function testRender(): void
    {
        $startDates = ['2025-01-02', '2025-01-05', '2025-01-11', '2025-01-18'];
        foreach ($startDates as $index => $startDate) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $index))
                    ->withStartDateTime(SerializableDateTime::fromString($startDate.' 10:00:00'))
                    ->withSportType(SportType::RUN)
                    ->withDistance(Kilometer::from(10))
                    ->build(),
                []
            ));
        }

        $this->assertMatchesHtmlSnapshot(
            $this->widget->render(
                dashboardWidgetId: DashboardWidgetId::fromUnprefixed('test'),
                now: SerializableDateTime::fromString('2025-01-20'),
                configuration: WidgetConfiguration::empty()
                    ->add('rollingWindowInDays', 7)
                    ->add('metricsDisplayOrder', array_map(fn (StatsContext $context) => $context->value, StatsContext::defaultSortingOrder()))
            )
        );
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testGuardValidConfigurationItShouldThrow(WidgetConfiguration $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidDashboardLayout($expectedException));
        $this->widget->guardValidConfiguration($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        yield 'missing "rollingWindowInDays" key' => [WidgetConfiguration::empty(), 'Configuration item "rollingWindowInDays" is required for RollingStatsWidget.'];

        $config = WidgetConfiguration::empty()->add('rollingWindowInDays', '7');
        yield 'invalid "rollingWindowInDays" key' => [$config, 'Configuration item "rollingWindowInDays" must be an integer.'];

        $config = WidgetConfiguration::empty()->add('rollingWindowInDays', 1);
        yield 'too small "rollingWindowInDays" key' => [$config, 'Configuration item "rollingWindowInDays" must be set to a value of 2 or greater.'];

        $config = WidgetConfiguration::empty()->add('rollingWindowInDays', 400);
        yield 'too big "rollingWindowInDays" key' => [$config, 'Configuration item "rollingWindowInDays" must be set to a value of 365 or lower.'];

        $config = WidgetConfiguration::empty()->add('rollingWindowInDays', 7);
        yield 'missing "metricsDisplayOrder" key' => [$config, 'Configuration item "metricsDisplayOrder" is required for RollingStatsWidget.'];

        $config = WidgetConfiguration::empty()
            ->add('rollingWindowInDays', 7)
            ->add('metricsDisplayOrder', 'invalid');
        yield 'invalid "metricsDisplayOrder" key' => [$config, 'Configuration item "metricsDisplayOrder" must be an array.'];

        $config = WidgetConfiguration::empty()
            ->add('rollingWindowInDays', 7)
            ->add('metricsDisplayOrder', [1, 2, 3, 4]);
        yield 'invalid number of items in "metricsDisplayOrder"' => [$config, 'Configuration item "metricsDisplayOrder" must contain all 3 metrics.'];

        $config = WidgetConfiguration::empty()
            ->add('rollingWindowInDays', 7)
            ->add('metricsDisplayOrder', ['test', 2, 3]);
        yield 'invalid value in "metricsDisplayOrder"' => [$config, 'Configuration item "metricsDisplayOrder" contains invalid value "test".'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(RollingStatsWidget::class);
    }
}

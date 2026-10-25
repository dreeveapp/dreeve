<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\DaytimeStats\Daytime;
use App\Domain\Dashboard\Widget\DaytimeStats\DaytimeStats;
use App\Domain\Dashboard\Widget\DaytimeStats\DayTimeStatsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class DayTimeStatsWidgetTest extends ContainerTestCase
{
    private DayTimeStatsWidget $widget;

    public function testGuardValidConfigurationAcceptsANightThatStartsAfterMidnight(): void
    {
        $this->widget->guardValidConfiguration(WidgetConfiguration::empty()->add('morningStartHour', 4)->add('afternoonStartHour', 11)->add('eveningStartHour', 17)->add('nightStartHour', 1));
        $this->addToAssertionCount(1);
    }

    public function testDaytimeStatsUsesTheConfiguredStartHours(): void
    {
        $activities = Activities::empty();
        foreach (['2025-01-01 00:30:00', '2025-01-01 03:59:00', '2025-01-01 04:00:00', '2025-01-01 11:00:00', '2025-01-01 23:00:00'] as $index => $startDateTime) {
            $activities->add(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed((string) $index))
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->build());
        }

        $statistics = DaytimeStats::create($activities, [
            Daytime::MORNING->value => 4,
            Daytime::AFTERNOON->value => 11,
            Daytime::EVENING->value => 17,
            Daytime::NIGHT->value => 1,
        ])->getData();

        $this->assertSame(
            [
                Daytime::MORNING->value => [1, 4, 11],
                Daytime::AFTERNOON->value => [1, 11, 17],
                Daytime::EVENING->value => [2, 17, 1],
                Daytime::NIGHT->value => [1, 1, 4],
            ],
            array_map(
                static fn (array $statistic): array => [$statistic['numberOfWorkouts'], $statistic['startHour'], $statistic['endHour']],
                $statistics,
            ),
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
        yield 'missing "morningStartHour" key' => [WidgetConfiguration::empty(), 'Configuration item "morningStartHour" is required for DayTimeStatsWidget.'];
        yield 'invalid "afternoonStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 'lol')->add('eveningStartHour', 17)->add('nightStartHour', 23), 'Configuration item "afternoonStartHour" must be an integer.'];
        yield 'too small "eveningStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', -1)->add('nightStartHour', 23), 'Configuration item "eveningStartHour" must be between 0 and 23.'];
        yield 'too large "nightStartHour" key' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 17)->add('nightStartHour', 24), 'Configuration item "nightStartHour" must be between 0 and 23.'];
        yield 'afternoon before morning' => [WidgetConfiguration::empty()->add('morningStartHour', 12)->add('afternoonStartHour', 6)->add('eveningStartHour', 17)->add('nightStartHour', 23), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
        yield 'night past morning' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 17)->add('nightStartHour', 7), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
        yield 'two daytimes start at the same hour' => [WidgetConfiguration::empty()->add('morningStartHour', 6)->add('afternoonStartHour', 12)->add('eveningStartHour', 12)->add('nightStartHour', 23), 'The daytime start hours must follow each other in the order morning, afternoon, evening, night.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(DayTimeStatsWidget::class);
    }
}

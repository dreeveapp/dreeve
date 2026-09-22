<?php

namespace App\Tests\Domain\Dashboard\Widget\RollingStats;

use App\Domain\Activity\Activities;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\RollingStats\RollingStatsChart;
use App\Domain\Dashboard\Widget\RollingStats\RollingWindows;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RollingStatsChartTest extends TestCase
{
    public function testItSumsThePrecedingDaysInclusive(): void
    {
        $chart = $this->buildChart(RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 3,
        ));

        $this->assertEquals([1.0, 1.0, 3.0, 2.0, 6.0], $chart['series'][0]['data']);
    }

    public function testASingleDayWindowEqualsTheDailyTotals(): void
    {
        $chart = $this->buildChart(RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 1,
        ));

        $this->assertEquals([1.0, 0.0, 2.0, 0.0, 4.0], $chart['series'][0]['data']);
    }

    public function testEveryDayIsLabelledSoTheAxisStaysReadable(): void
    {
        $chart = $this->buildChart(RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 3,
        ));

        $this->assertEquals(['', 'Jan 02', 'Jan 03', 'Jan 04', 'Jan 05'], $chart['xAxis'][0]['data']);
        $this->assertSame('auto', $chart['xAxis'][0]['axisLabel']['interval']);
    }

    public function testTheZoomNeverStartsBeforeTheFirstWindow(): void
    {
        $chart = $this->buildChart(RollingWindows::create(
            startDate: SerializableDateTime::fromString('2025-01-01'),
            now: SerializableDateTime::fromString('2025-01-05'),
            windowInDays: 3,
        ));

        $this->assertSame(0, $chart['dataZoom'][0]['endValue']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChart(RollingWindows $rollingWindows): array
    {
        $distancePerDay = [
            '2025-01-01' => 1,
            '2025-01-03' => 2,
            '2025-01-05' => 4,
        ];

        $activities = Activities::empty();
        foreach (array_values($distancePerDay) as $index => $distance) {
            $activities->add(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed((string) $index))
                ->withStartDateTime(SerializableDateTime::fromString(array_keys($distancePerDay)[$index].' 10:00:00'))
                ->withDistance(Kilometer::from($distance))
                ->build());
        }

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return RollingStatsChart::create(
            activities: $activities,
            unitSystem: UnitSystem::METRIC,
            activityType: ActivityType::RIDE,
            metricsDisplayOrder: StatsContext::defaultSortingOrder(),
            rollingWindows: $rollingWindows,
            translator: $translator,
        )->build();
    }
}

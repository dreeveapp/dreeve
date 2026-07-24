<?php

namespace App\Tests\Domain\Dashboard\Widget\YearlyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStatsPerDay\FindYearlyStatsPerDayResponse;
use App\Domain\Dashboard\Widget\YearlyStats\YearlyStatisticsChart;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class YearlyStatisticsChartTest extends TestCase
{
    public function testChartDataNeverDecreasesAcrossMonthBoundaries(): void
    {
        $response = FindYearlyStatsPerDayResponse::empty();

        $cumulativeDistance = 0;
        $cumulativeMovingTime = 0;
        $cumulativeElevation = 0;

        for ($month = 1; $month <= 6; ++$month) {
            $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, 2025));
            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $cumulativeDistance += 10000;
                $cumulativeMovingTime += 3600;
                $cumulativeElevation += 100;

                $response->add(
                    date: SerializableDateTime::fromString(sprintf('2025-%02d-%02d', $month, $day)),
                    activityType: ActivityType::RIDE,
                    distance: Meter::from($cumulativeDistance)->toKilometer(),
                    movingTime: Seconds::from($cumulativeMovingTime),
                    elevation: Meter::from($cumulativeElevation),
                );
            }
        }

        $years = Years::empty();
        $years->add(Year::fromInt(2025));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $now = SerializableDateTime::fromString('2025-06-30');

        foreach (StatsContext::cases() as $context) {
            $chart = YearlyStatisticsChart::create(
                yearStats: $response,
                uniqueYears: $years,
                activityType: ActivityType::RIDE,
                context: $context,
                unitSystem: UnitSystem::METRIC,
                translator: $translator,
                now: $now,
                enableLastXYearsByDefault: 10,
            );

            $result = $chart->build();
            $data = $result['series'][0]['data'];

            for ($i = 1; $i < count($data); ++$i) {
                if ($data[$i] < $data[$i - 1]) {
                    $this->fail('Detected downwards trent in yearly stats chart');
                }
            }
        }

        $this->addToAssertionCount(1);
    }
}

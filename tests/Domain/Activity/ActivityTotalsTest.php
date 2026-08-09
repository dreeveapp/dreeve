<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\FindActivityTotals\FindActivityTotalsResponse;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActivityTotalsTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'provideFirstActivityStartDates')]
    public function testGetTotalDaysSinceFirstActivity(string $expectedResult, string $firstActivityStartDate, string $now): void
    {
        $this->assertEquals(
            $expectedResult,
            $this->activityTotals(
                firstActivityStartDate: SerializableDateTime::fromString($firstActivityStartDate),
                now: SerializableDateTime::fromString($now),
            )->getTotalDaysSinceFirstActivity()
        );
    }

    public static function provideFirstActivityStartDates(): \Generator
    {
        yield ['1 day', '2023-11-24', '2023-11-25'];
        yield ['3 weeks and 3 days', '2023-11-01', '2023-11-25'];
        yield ['7 months and 1 day', '2023-04-24', '2023-11-25'];
        yield ['1 year and 1 day', '2022-11-24', '2023-11-25'];
        yield ['6 years and 1 day', '2017-11-24', '2023-11-25'];
    }

    public function testGetStartDateWithoutActivities(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('No activities found');

        $this->activityTotals(firstActivityStartDate: null)->getStartDate();
    }

    public function testItExposesTheTotalsItWasGiven(): void
    {
        $activityTotals = $this->activityTotals(
            firstActivityStartDate: SerializableDateTime::fromString('2023-11-24'),
            now: SerializableDateTime::fromString('2023-11-25'),
        );

        $this->assertEquals(Kilometer::from(1000), $activityTotals->getDistance());
        $this->assertEquals(Meter::from(500), $activityTotals->getElevation());
        $this->assertEquals(300, $activityTotals->getCalories());
        $this->assertEquals(10, $activityTotals->getTotalActivities());
        $this->assertEquals(2, $activityTotals->getMovingTimeInHours());
        $this->assertEquals(7, $activityTotals->getTotalDaysOfWorkingOut());
        $this->assertEquals(Kilometer::from(1000), $activityTotals->getDailyAverage());
    }

    private function activityTotals(
        ?SerializableDateTime $firstActivityStartDate,
        ?SerializableDateTime $now = null,
    ): ActivityTotals {
        return ActivityTotals::create(
            totals: new FindActivityTotalsResponse(
                totalActivities: 10,
                totalDistance: Kilometer::from(1000),
                totalElevation: Meter::from(500),
                totalCalories: 300,
                totalMovingTimeInSeconds: 7200,
                totalDaysOfWorkingOut: 7,
                firstActivityStartDate: $firstActivityStartDate,
            ),
            now: $now ?? SerializableDateTime::fromString('2023-11-25'),
            translator: $this->getContainer()->get(TranslatorInterface::class),
        );
    }
}

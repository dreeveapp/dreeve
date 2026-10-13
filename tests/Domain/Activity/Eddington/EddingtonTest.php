<?php

namespace App\Tests\Domain\Activity\Eddington;

use App\Domain\Activity\Eddington\Config\EddingtonConfigItem;
use App\Domain\Activity\Eddington\Eddington;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Measurement\UnitSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EddingtonTest extends TestCase
{
    /**
     * @param list<int> $expectedUpcomingNumbers
     */
    #[DataProvider(methodName: 'provideUpcomingNumbers')]
    public function testGetUpcomingNumbers(int $eddingtonNumber, array $expectedUpcomingNumbers): void
    {
        $eddington = Eddington::create(
            config: EddingtonConfigItem::create(
                label: 'Ride',
                sportTypesToInclude: SportTypes::empty(),
                showInDashboardWidget: false
            ),
            unitSystem: UnitSystem::METRIC,
            eddingtonNumber: $eddingtonNumber,
            longestDistanceInADay: 80,
            timesCompletedData: [],
            history: [],
            daysToCompleteForFutureNumbers: [],
        );

        $this->assertSame($expectedUpcomingNumbers, $eddington->getUpcomingNumbers());
    }

    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function provideUpcomingNumbers(): iterable
    {
        yield 'milestones follow the consecutive numbers' => [68, [69, 70, 71, 72, 73, 80, 90, 100, 110, 120]];
        yield 'last consecutive number is a multiple of 10' => [65, [66, 67, 68, 69, 70, 80, 90, 100, 110, 120]];
        yield 'last consecutive number is just below a multiple of 10' => [64, [65, 66, 67, 68, 69, 70, 80, 90, 100, 110]];
        yield 'no eddington number yet' => [0, [1, 2, 3, 4, 5, 10, 20, 30, 40, 50]];
    }

    public function testGetTimesCompletedFor(): void
    {
        $eddington = Eddington::create(
            config: EddingtonConfigItem::create(
                label: 'Ride',
                sportTypesToInclude: SportTypes::empty(),
                showInDashboardWidget: false
            ),
            unitSystem: UnitSystem::METRIC,
            eddingtonNumber: 3,
            longestDistanceInADay: 5,
            timesCompletedData: [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1],
            history: [],
            daysToCompleteForFutureNumbers: [],
        );

        $this->assertSame(2, $eddington->getTimesCompletedFor(4));
        $this->assertSame(1, $eddington->getTimesCompletedFor(5));
        $this->assertSame(0, $eddington->getTimesCompletedFor(10));
    }
}

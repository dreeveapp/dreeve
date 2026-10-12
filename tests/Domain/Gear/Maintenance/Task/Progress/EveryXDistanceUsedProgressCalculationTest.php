<?php

namespace App\Tests\Domain\Gear\Maintenance\Task\Progress;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Domain\Gear\Maintenance\StartingValue;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\Progress\EveryXDistanceUsedProgressCalculation;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgress;
use App\Domain\Gear\Maintenance\Task\Progress\ProgressCalculationContext;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

class EveryXDistanceUsedProgressCalculationTest extends ContainerTestCase
{
    private EveryXDistanceUsedProgressCalculation $calculation;

    #[DataProvider('provideIntervalUnits')]
    public function testCalculate(IntervalUnit $intervalUnit, MaintenanceTaskProgress $expectedProgress): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('last-tagged'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 01:00:00'))
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('include-2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withDistance(Kilometer::from(150))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::random())
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:00:00'))
                ->withDistance(Kilometer::from(100))
                ->build(),
            []
        ));

        $this->assertEquals(
            $expectedProgress,
            $this->calculation->calculate(
                ProgressCalculationContext::from(
                    usageWindows: UsageWindows::fromArray([UsageWindow::create(gearId: GearId::fromUnprefixed('test'), from: null, until: null, sportTypes: SportTypes::empty())]),
                    lastTaggedOn: SerializableDateTime::fromString('01-01-2025'),
                    intervalUnit: $intervalUnit,
                    intervalValue: 1000,
                    startingValue: null,
                )
            )
        );
    }

    /**
     * @return iterable<string, array{IntervalUnit, MaintenanceTaskProgress}>
     */
    public static function provideIntervalUnits(): iterable
    {
        yield 'kilometers' => [IntervalUnit::EVERY_X_KILOMETERS_USED, MaintenanceTaskProgress::from(250, 1000, '250 km', '750 km')];
        yield 'miles' => [IntervalUnit::EVERY_X_MILES_USED, MaintenanceTaskProgress::from(250 * Kilometer::FACTOR_TO_MILES, 1000, '155 mi', '844 mi')];
    }

    /**
     * @param array<int, array{string, ?string, ?string}> $windows
     */
    #[DataProvider('provideUsageWindows')]
    public function testCalculateOnlyCountsActivitiesWithinUsageWindows(array $windows, string $expectedDescription): void
    {
        $activities = [
            ['before-last-tagged', 'bike-a', '2024-12-01 10:00:00', 640],
            ['bike-a-january', 'bike-a', '2025-01-10 10:00:00', 10],
            ['bike-a-swap-day', 'bike-a', '2025-03-15 08:00:00', 20],
            ['bike-a-june', 'bike-a', '2025-06-01 10:00:00', 40],
            ['bike-b-march', 'bike-b', '2025-03-20 10:00:00', 80],
            ['bike-b-december', 'bike-b', '2025-12-01 10:00:00', 160],
        ];
        foreach ($activities as [$activityId, $gearId, $startDateTime, $distanceInKm]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withGearId(GearId::fromUnprefixed($gearId))
                    ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                    ->withDistance(Kilometer::from($distanceInKm))
                    ->build(),
                []
            ));
        }

        $progress = $this->calculation->calculate(
            ProgressCalculationContext::from(
                usageWindows: UsageWindows::fromArray(array_map(
                    static fn (array $window): UsageWindow => UsageWindow::create(
                        gearId: GearId::fromUnprefixed($window[0]),
                        from: $window[1] ? SerializableDateTime::fromString($window[1]) : null,
                        until: $window[2] ? SerializableDateTime::fromString($window[2]) : null,
                        sportTypes: SportTypes::empty(),
                    ),
                    $windows,
                )),
                lastTaggedOn: SerializableDateTime::fromString('2025-01-01'),
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
                intervalValue: 1000,
                startingValue: null,
            )
        );

        $this->assertSame($expectedDescription, $progress->getDescription());
    }

    /**
     * @return iterable<string, array{array<int, array{string, ?string, ?string}>, string}>
     */
    public static function provideUsageWindows(): iterable
    {
        yield 'open window' => [[['bike-a', null, null]], '70 km'];
        yield 'closed window excludes the day it ends' => [[['bike-a', null, '2025-03-15']], '10 km'];
        yield 'swap day counts for the new window' => [[['bike-a', null, '2025-03-15'], ['bike-b', '2025-03-15', null]], '250 km'];
        yield 'seasonal windows on one bike' => [[['bike-a', null, '2025-02-01'], ['bike-a', '2025-05-01', null]], '50 km'];
        yield 'overlapping windows count an activity once' => [[['bike-a', null, null], ['bike-a', '2025-01-01', null], ['bike-b', null, null]], '310 km'];
        yield 'no windows' => [[], '0 km'];
    }

    /**
     * @param array<int, array{string, list<SportType>}> $windows
     */
    #[DataProvider('provideSportTypeWindows')]
    public function testCalculateOnlyCountsActivitiesOfTheUsageWindowSportTypes(array $windows, string $expectedDescription): void
    {
        $activities = [
            ['outdoor-ride', SportType::RIDE, 10],
            ['indoor-ride', SportType::VIRTUAL_RIDE, 20],
            ['gravel-ride', SportType::GRAVEL_RIDE, 40],
        ];
        foreach ($activities as [$activityId, $sportType, $distanceInKm]) {
            $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withGearId(GearId::fromUnprefixed('bike-a'))
                    ->withSportType($sportType)
                    ->withStartDateTime(SerializableDateTime::fromString('2025-02-01 10:00:00'))
                    ->withDistance(Kilometer::from($distanceInKm))
                    ->build(),
                []
            ));
        }

        $progress = $this->calculation->calculate(
            ProgressCalculationContext::from(
                usageWindows: UsageWindows::fromArray(array_map(
                    static fn (array $window): UsageWindow => UsageWindow::create(
                        gearId: GearId::fromUnprefixed($window[0]),
                        from: null,
                        until: null,
                        sportTypes: SportTypes::fromArray($window[1]),
                    ),
                    $windows,
                )),
                lastTaggedOn: SerializableDateTime::fromString('2025-01-01'),
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
                intervalValue: 1000,
                startingValue: null,
            )
        );

        $this->assertSame($expectedDescription, $progress->getDescription());
    }

    /**
     * @return iterable<string, array{array<int, array{string, list<SportType>}>, string}>
     */
    public static function provideSportTypeWindows(): iterable
    {
        yield 'all sport types' => [[['bike-a', []]], '70 km'];
        yield 'outdoor only' => [[['bike-a', [SportType::RIDE, SportType::GRAVEL_RIDE]]], '50 km'];
        yield 'indoor only' => [[['bike-a', [SportType::VIRTUAL_RIDE]]], '20 km'];
        yield 'separate windows per sport type' => [[['bike-a', [SportType::RIDE]], ['bike-a', [SportType::VIRTUAL_RIDE]]], '30 km'];
        yield 'sport type the gear was not used for' => [[['bike-a', [SportType::RUN]]], '0 km'];
    }

    public function testCalculateAddsTheStartingValue(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride'))
                ->withGearId(GearId::fromUnprefixed('test'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-02 10:00:00'))
                ->withDistance(Kilometer::from(100))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));

        $progress = $this->calculation->calculate(
            ProgressCalculationContext::from(
                usageWindows: UsageWindows::fromArray([UsageWindow::create(gearId: GearId::fromUnprefixed('test'), from: null, until: null, sportTypes: SportTypes::empty())]),
                lastTaggedOn: SerializableDateTime::fromString('2025-01-01'),
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
                intervalValue: 1000,
                startingValue: StartingValue::create(distance: Meter::from(250000), movingTime: Seconds::from(7200)),
            )
        );

        $this->assertSame('350 km', $progress->getDescription());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->calculation = new EveryXDistanceUsedProgressCalculation(
            $this->getContainer()->get(Connection::class),
        );
    }
}

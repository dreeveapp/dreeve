<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityImagesHaveBeenUpdated;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRouteWasUpdated;
use App\Domain\Activity\ActivityWasAdded;
use App\Domain\Activity\ActivityWasUpdated;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityTest extends TestCase
{
    use MatchesSnapshots;

    public function testGetName(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withName('Zwift - Test Activity #hashtag')
            ->build();

        $this->assertEquals('Test Activity #hashtag', $activity->getName());
        $this->assertEquals('Zwift - Test Activity #hashtag', $activity->getOriginalName());

        $activity = ActivityBuilder::fromDefaults()
            ->withName('Morning ride #sfs-chain-lubed #sfs-di-2-charged #fun')
            ->build();

        $this->assertEquals('Morning ride #fun', $activity->getName());
        $this->assertEquals('Morning ride #sfs-chain-lubed #sfs-di-2-charged #fun', $activity->getOriginalName());
    }

    public function testLeafletMapWithoutStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline('line')
            ->withWorldType(WorldType::ZWIFT)
            ->build();

        $this->assertNull($activity->getLeafletMap());
    }

    public function testLeafletMapWhenZwiftMapCouldNotBeDetermined(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->withStartingCoordinate(
                Coordinate::createFromLatAndLng(Latitude::fromString('1'), Longitude::fromString('1'))
            )
            ->withPolyline('line')
            ->build();

        $this->assertNull($activity->getLeafletMap());
    }

    public function testGetPaceInSecPer100Meter(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withAverageSpeed(KmPerHour::from(10))
            ->build();

        $this->assertEquals(
            SecPer100Meter::from(35.9971),
            $activity->getPaceInSecPer100Meter()
        );
    }

    public function testWithLocalImagePathsItShouldRecordThatTheImagesWereUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withLocalImagePaths('/activities/one.jpg')
            ->build();

        $updatedActivity = $activity->withLocalImagePaths(['/activities/one.jpg', '/activities/two.jpg']);

        $this->assertEquals(
            [new ActivityImagesHaveBeenUpdated(ActivityId::fromUnprefixed('903645'), SerializableDateTime::fromString('2023-10-10'))],
            $updatedActivity->getRecordedEvents()
        );
        $this->assertEmpty($activity->getRecordedEvents());
    }

    public function testWithLocalImagePathsItShouldRecordNothingWhenTheImagesDidNotChange(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withLocalImagePaths('/activities/one.jpg')
            ->build();

        $this->assertEmpty(
            $activity->withLocalImagePaths(['/activities/one.jpg'])->getRecordedEvents()
        );
    }

    public function testWithLocalImagePathsItShouldRecordNothingWhenOnlyTheLeadingSlashDiffers(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withLocalImagePaths('activities/one.jpg')
            ->build();

        $this->assertEmpty(
            $activity->withLocalImagePaths(['/activities/one.jpg'])->getRecordedEvents()
        );
    }

    public function testWithLocalImagePathsItShouldRecordThatTheImagesWereRemoved(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withLocalImagePaths('/activities/one.jpg')
            ->build();

        $this->assertEquals(
            [new ActivityImagesHaveBeenUpdated(ActivityId::fromUnprefixed('903645'), SerializableDateTime::fromString('2023-10-10'))],
            $activity->withLocalImagePaths([])->getRecordedEvents()
        );
    }

    public function testHasMappableRoute(): void
    {
        $this->assertTrue(ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->build()->hasMappableRoute());
    }

    #[DataProvider('provideActivitiesThatAreNotOnTheMap')]
    public function testHasMappableRouteWhenItIsNotOnTheMap(ActivityBuilder $builder): void
    {
        $this->assertFalse($builder->build()->hasMappableRoute());
    }

    /**
     * @return \Generator<string, array{ActivityBuilder}>
     */
    public static function provideActivitiesThatAreNotOnTheMap(): \Generator
    {
        yield 'sport type does not support reverse geocoding' => [
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->withSportType(SportType::VIRTUAL_RIDE),
        ];
        yield 'not recorded in the real world' => [
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->withWorldType(WorldType::ZWIFT),
        ];
        yield 'without polyline' => [
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->withPolyline(null),
        ];
        yield 'with empty polyline' => [
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->withPolyline(''),
        ];
        yield 'without reverse geocoded country' => [
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->withRouteGeography(RouteGeography::create([])),
        ];
    }

    #[DataProvider('provideRouteChanges')]
    public function testItShouldRecordThatTheRouteWasUpdated(callable $change): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->build();

        $this->assertContainsEquals(
            new ActivityRouteWasUpdated(),
            $change($activity)->getRecordedEvents()
        );
        $this->assertEmpty($activity->getRecordedEvents());
    }

    /**
     * @return \Generator<string, array{callable(Activity): Activity}>
     */
    public static function provideRouteChanges(): \Generator
    {
        yield 'polyline' => [fn (Activity $activity): Activity => $activity->withPolyline('other-polyline')];
        yield 'route geography' => [fn (Activity $activity): Activity => $activity->withRouteGeography(
            RouteGeography::create(['country_code' => 'NL'])
        )];
        yield 'sport type' => [fn (Activity $activity): Activity => $activity->withSportType(SportType::RUN)];
        yield 'world type' => [fn (Activity $activity): Activity => $activity->withWorldType(WorldType::ZWIFT)];
        yield 'name' => [fn (Activity $activity): Activity => $activity->withName(ActivityName::fromString('Renamed'))];
        yield 'distance' => [fn (Activity $activity): Activity => $activity->withDistance(Kilometer::from(42))];
        yield 'start date' => [fn (Activity $activity): Activity => $activity->withStartDateTime(
            SerializableDateTime::fromString('2024-01-01')
        )];
        yield 'commute' => [fn (Activity $activity): Activity => $activity->withCommute(true)];
        yield 'workout type' => [fn (Activity $activity): Activity => $activity->withWorkoutType(WorkoutType::RACE)];
    }

    #[DataProvider('provideRouteNonChanges')]
    public function testItShouldNotRecordThatTheRouteWasUpdatedWhenTheRouteDidNotChange(callable $change): void
    {
        $this->assertNotContainsEquals(new ActivityRouteWasUpdated(), $change(ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->build())->getRecordedEvents());
    }

    /**
     * @return \Generator<string, array{callable(Activity): Activity}>
     */
    public static function provideRouteNonChanges(): \Generator
    {
        yield 'the same polyline' => [fn (Activity $activity): Activity => $activity->withPolyline('tqafAua~y^vG{D')];
        yield 'the same route geography' => [fn (Activity $activity): Activity => $activity->withRouteGeography(
            RouteGeography::create(['country_code' => 'BE'])
        )];
        yield 'the same name' => [fn (Activity $activity): Activity => $activity->withName(
            ActivityName::fromString('Test activity')
        )];
        yield 'a property the map does not care about' => [
            fn (Activity $activity): Activity => $activity->withDeviceName('Wahoo'),
        ];
    }

    public function testItShouldNotRecordThatTheRouteWasUpdatedWhenTheActivityIsNotOnTheMap(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->build();

        $this->assertNotContainsEquals(
            new ActivityRouteWasUpdated(),
            $activity->withName(ActivityName::fromString('Renamed'))->getRecordedEvents()
        );
    }

    #[DataProvider('provideUpdates')]
    public function testItShouldRecordThatTheActivityWasUpdated(callable $change): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertContainsEquals(
            new ActivityWasUpdated(
                ActivityId::fromUnprefixed('903645'),
                SerializableDateTime::fromString('2023-10-10'),
                SerializableDateTime::fromString('2023-10-10')
            ),
            $change($activity)->getRecordedEvents()
        );
        $this->assertEmpty($activity->getRecordedEvents());
    }

    /**
     * @return \Generator<string, array{callable(Activity): Activity}>
     */
    public static function provideUpdates(): \Generator
    {
        yield 'name' => [fn (Activity $activity): Activity => $activity->withName(ActivityName::fromString('Renamed'))];
        yield 'description' => [fn (Activity $activity): Activity => $activity->withDescription('Updated')];
        yield 'device name' => [fn (Activity $activity): Activity => $activity->withDeviceName('Wahoo')];
        yield 'sport type' => [fn (Activity $activity): Activity => $activity->withSportType(SportType::RUN)];
        yield 'world type' => [fn (Activity $activity): Activity => $activity->withWorldType(WorldType::ZWIFT)];
        yield 'distance' => [fn (Activity $activity): Activity => $activity->withDistance(Kilometer::from(42))];
        yield 'elevation' => [fn (Activity $activity): Activity => $activity->withElevation(Meter::from(666))];
        yield 'average speed' => [fn (Activity $activity): Activity => $activity->withAverageSpeed(KmPerHour::from(42))];
        yield 'max speed' => [fn (Activity $activity): Activity => $activity->withMaxSpeed(KmPerHour::from(42))];
        yield 'moving time' => [fn (Activity $activity): Activity => $activity->withMovingTimeInSeconds(4242)];
        yield 'elapsed time' => [fn (Activity $activity): Activity => $activity->withElapsedTimeInSeconds(4242)];
        yield 'polyline' => [fn (Activity $activity): Activity => $activity->withPolyline('other-polyline')];
        yield 'starting coordinate' => [fn (Activity $activity): Activity => $activity->withStartingCoordinate(
            Coordinate::createFromLatAndLng(Latitude::fromString('1'), Longitude::fromString('2'))
        )];
        yield 'route geography' => [fn (Activity $activity): Activity => $activity->withRouteGeography(
            RouteGeography::create(['country_code' => 'NL'])
        )];
        yield 'gear' => [fn (Activity $activity): Activity => $activity->withGear(GearId::fromUnprefixed('42'))];
        yield 'commute' => [fn (Activity $activity): Activity => $activity->withCommute(true)];
        yield 'workout type' => [fn (Activity $activity): Activity => $activity->withWorkoutType(WorkoutType::RACE)];
    }

    #[DataProvider('provideNonUpdates')]
    public function testItShouldRecordNothingWhenNothingChanged(callable $change): void
    {
        $this->assertEmpty($change(ActivityBuilder::fromDefaults()->build())->getRecordedEvents());
    }

    /**
     * @return \Generator<string, array{callable(Activity): Activity}>
     */
    public static function provideNonUpdates(): \Generator
    {
        // An import rewriting the same values must not invalidate anything.
        yield 'the same name' => [fn (Activity $activity): Activity => $activity->withName(
            ActivityName::fromString('Test activity')
        )];
        yield 'the same distance' => [fn (Activity $activity): Activity => $activity->withDistance(Kilometer::from(10))];
        yield 'the same start date' => [fn (Activity $activity): Activity => $activity->withStartDateTime(
            SerializableDateTime::fromString('2023-10-10')
        )];
        // Enrichment happens on every read, it may never record anything.
        yield 'the gear name' => [fn (Activity $activity): Activity => $activity->withGearName('Race bike')];
        yield 'the normalized power' => [fn (Activity $activity): Activity => $activity->withNormalizedPower(242)];
    }

    public function testItShouldRecordThatTheActivityWasUpdatedOnlyOnce(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertEquals(
            [new ActivityWasUpdated(
                ActivityId::fromUnprefixed('903645'),
                SerializableDateTime::fromString('2023-10-10'),
                SerializableDateTime::fromString('2023-10-10')
            )],
            $activity
                ->withName(ActivityName::fromString('Renamed'))
                ->withDescription('Updated')
                ->withDeviceName('Wahoo')
                ->withMovingTimeInSeconds(4242)
                ->getRecordedEvents()
        );
    }

    public function testItShouldRecordBothYearsWhenTheActivityMovesToAnotherYear(): void
    {
        $updatedActivity = ActivityBuilder::fromDefaults()
            ->build()
            ->withStartDateTime(SerializableDateTime::fromString('2021-05-05'));

        $events = $updatedActivity->getRecordedEvents();
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertInstanceOf(ActivityWasUpdated::class, $event);
        $this->assertEquals([Year::fromInt(2021), Year::fromInt(2023)], $event->getYears());
    }

    public function testItShouldRecordOneYearWhenTheActivityStaysInTheSameYear(): void
    {
        $updatedActivity = ActivityBuilder::fromDefaults()
            ->build()
            ->withStartDateTime(SerializableDateTime::fromString('2023-05-05'));

        $events = $updatedActivity->getRecordedEvents();
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertInstanceOf(ActivityWasUpdated::class, $event);
        $this->assertEquals([Year::fromInt(2023)], $event->getYears());
    }

    public function testItShouldRecordThatTheRouteWasUpdatedWhenTheActivityIsCreatedOnTheMap(): void
    {
        $this->assertEquals(
            [new ActivityWasAdded(SerializableDateTime::fromString('2023-10-10')), new ActivityRouteWasUpdated()],
            ActivityBuilder::fromDefaults()
                ->withSportType(SportType::RIDE)
                ->withWorldType(WorldType::REAL_WORLD)
                ->withPolyline('tqafAua~y^vG{D')
                ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))->buildAsNewlyCreated()->getRecordedEvents()
        );
    }

    public function testItShouldOnlyRecordThatTheActivityWasAddedWhenItIsCreatedOffTheMap(): void
    {
        $this->assertEquals(
            [new ActivityWasAdded(SerializableDateTime::fromString('2023-10-10'))],
            ActivityBuilder::fromDefaults()->buildAsNewlyCreated()->getRecordedEvents()
        );
    }
}

<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityImagesHaveBeenUpdated;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRouteWasUpdated;
use App\Domain\Activity\ActivityWasAdded;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
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
            [new ActivityImagesHaveBeenUpdated()],
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
            [new ActivityImagesHaveBeenUpdated()],
            $activity->withLocalImagePaths([])->getRecordedEvents()
        );
    }

    public function testHasMappableRoute(): void
    {
        $this->assertTrue($this->activityOnTheMap()->build()->hasMappableRoute());
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
            self::activityOnTheMap()->withSportType(SportType::VIRTUAL_RIDE),
        ];
        yield 'not recorded in the real world' => [
            self::activityOnTheMap()->withWorldType(WorldType::ZWIFT),
        ];
        yield 'without polyline' => [
            self::activityOnTheMap()->withPolyline(null),
        ];
        yield 'with empty polyline' => [
            self::activityOnTheMap()->withPolyline(''),
        ];
        yield 'without reverse geocoded country' => [
            self::activityOnTheMap()->withRouteGeography(RouteGeography::create([])),
        ];
    }

    #[DataProvider('provideRouteChanges')]
    public function testItShouldRecordThatTheRouteWasUpdated(callable $change): void
    {
        $activity = $this->activityOnTheMap()->build();

        $this->assertEquals(
            [new ActivityRouteWasUpdated()],
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
    public function testItShouldRecordNothingWhenTheRouteDidNotChange(callable $change): void
    {
        $this->assertEmpty($change($this->activityOnTheMap()->build())->getRecordedEvents());
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

    public function testItShouldRecordNothingWhenTheActivityIsNotOnTheMap(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->build();

        $this->assertEmpty($activity->withName(ActivityName::fromString('Renamed'))->getRecordedEvents());
    }

    public function testItShouldRecordThatTheRouteWasUpdatedWhenTheActivityIsCreatedOnTheMap(): void
    {
        $this->assertEquals(
            [new ActivityWasAdded(), new ActivityRouteWasUpdated()],
            $this->activityOnTheMap()->buildAsNewlyCreated()->getRecordedEvents()
        );
    }

    public function testItShouldOnlyRecordThatTheActivityWasAddedWhenItIsCreatedOffTheMap(): void
    {
        $this->assertEquals(
            [new ActivityWasAdded()],
            ActivityBuilder::fromDefaults()->buildAsNewlyCreated()->getRecordedEvents()
        );
    }

    private static function activityOnTheMap(): ActivityBuilder
    {
        return ActivityBuilder::fromDefaults()
            ->withSportType(SportType::RIDE)
            ->withWorldType(WorldType::REAL_WORLD)
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']));
    }
}

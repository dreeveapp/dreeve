<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\ActivityRouteLocator;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ActivityRouteLocatorTest extends ContainerTestCase
{
    /**
     * @param array<int, int>                      $times
     * @param array<int, array{float, float}|null> $latLngs
     */
    #[DataProvider('provideLocations')]
    public function testLocateAtDateTime(array $times, array $latLngs, string $on, Coordinate $expected): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 10:00:00'))
            ->build();
        $activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::TIME)
            ->withData($times)
            ->build());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData($latLngs)
            ->build());

        $this->assertEquals(
            $expected,
            new ActivityRouteLocator($activityStreamRepository)->locateAtDateTime(
                activity: $activity,
                on: SerializableDateTime::fromString($on),
            ),
        );
    }

    /**
     * @return iterable<string, array{array<int, int>, array<int, array{float, float}|null>, string, Coordinate}>
     */
    public static function provideLocations(): iterable
    {
        yield 'exact hit' => [
            [0, 10, 20],
            [[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]],
            '2026-06-01 10:00:10',
            Coordinate::createFromLatAndLng(Latitude::fromString('51'), Longitude::fromString('5')),
        ];
        yield 'between two samples' => [
            [0, 20],
            [[50.0, 4.0], [51.0, 5.0]],
            '2026-06-01 10:00:05',
            Coordinate::createFromLatAndLng(Latitude::fromString('50.25'), Longitude::fromString('4.25')),
        ];
        yield 'pause gap, closer to the pause' => [
            [0, 10, 3610],
            [[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]],
            '2026-06-01 10:20:00',
            Coordinate::createFromLatAndLng(Latitude::fromString('51'), Longitude::fromString('5')),
        ];
        yield 'pause gap, closer to the resume' => [
            [0, 10, 3610],
            [[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]],
            '2026-06-01 10:50:00',
            Coordinate::createFromLatAndLng(Latitude::fromString('52'), Longitude::fromString('6')),
        ];
        yield 'null latlng gap' => [
            [0, 10, 20, 30],
            [[50.0, 4.0], null, null, [51.0, 5.0]],
            '2026-06-01 10:00:15',
            Coordinate::createFromLatAndLng(Latitude::fromString('50.5'), Longitude::fromString('4.5')),
        ];
        yield 'null latlng at the start' => [
            [0, 10, 20],
            [null, [51.0, 5.0], [52.0, 6.0]],
            '2026-06-01 10:00:00',
            Coordinate::createFromLatAndLng(Latitude::fromString('51'), Longitude::fromString('5')),
        ];
        yield 'null latlng at the end' => [
            [0, 10, 20],
            [[50.0, 4.0], [51.0, 5.0], null],
            '2026-06-01 10:00:20',
            Coordinate::createFromLatAndLng(Latitude::fromString('51'), Longitude::fromString('5')),
        ];
    }

    #[DataProvider('provideMomentsOutsideTheActivity')]
    public function testLocateAtDateTimeReturnsNullOutsideTheActivity(string $on): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 10:00:00'))
            ->build();
        $activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::TIME)
            ->withData([0, 10, 20])
            ->build());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]])
            ->build());

        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAtDateTime(
            activity: $activity,
            on: SerializableDateTime::fromString($on),
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMomentsOutsideTheActivity(): iterable
    {
        yield 'before start' => ['2026-06-01 09:59:59'];
        yield 'after end' => ['2026-06-01 10:00:21'];
    }

    /**
     * @param array<int, int|float>                $distances
     * @param array<int, array{float, float}|null> $latLngs
     */
    #[DataProvider('provideDistances')]
    public function testLocateAtDistance(array $distances, array $latLngs, float $distance, Coordinate $expected): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::DISTANCE)
            ->withData($distances)
            ->build());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData($latLngs)
            ->build());

        $this->assertEquals(
            $expected,
            new ActivityRouteLocator($activityStreamRepository)->locateAtDistance(
                activity: $activity,
                distance: Meter::from($distance),
            ),
        );
    }

    /**
     * @return iterable<string, array{array<int, int|float>, array<int, array{float, float}|null>, float, Coordinate}>
     */
    public static function provideDistances(): iterable
    {
        yield 'exact hit' => [
            [0, 100, 200],
            [[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]],
            100.0,
            Coordinate::createFromLatAndLng(Latitude::fromString('51'), Longitude::fromString('5')),
        ];
        yield 'between two samples' => [
            [0.0, 400.5],
            [[50.0, 4.0], [51.0, 5.0]],
            100.125,
            Coordinate::createFromLatAndLng(Latitude::fromString('50.25'), Longitude::fromString('4.25')),
        ];
        yield 'large gap still interpolates' => [
            [0, 10000],
            [[50.0, 4.0], [51.0, 5.0]],
            7500.0,
            Coordinate::createFromLatAndLng(Latitude::fromString('50.75'), Longitude::fromString('4.75')),
        ];
        yield 'null latlng gap' => [
            [0, 100, 200, 300],
            [[50.0, 4.0], null, null, [51.0, 5.0]],
            150.0,
            Coordinate::createFromLatAndLng(Latitude::fromString('50.5'), Longitude::fromString('4.5')),
        ];
    }

    public function testLocateAtDistanceReturnsNullBeyondTheRoute(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::DISTANCE)
            ->withData([0, 100, 200])
            ->build());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]])
            ->build());

        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAtDistance(
            activity: $activity,
            distance: Meter::from(200.1),
        ));
    }

    public function testLocateAtDateTimeReturnsNullWithoutStreams(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 10:00:00'))
            ->build();
        $activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::TIME)
            ->withData([0, 10, 20])
            ->build());

        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAtDateTime(
            activity: $activity,
            on: SerializableDateTime::fromString('2026-06-01 10:00:10'),
        ));
        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAtDistance(
            activity: $activity,
            distance: Meter::from(10),
        ));
    }
}

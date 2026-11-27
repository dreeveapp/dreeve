<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Route\ActivityRouteLocator;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
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
    public function testLocateAt(array $times, array $latLngs, string $on, Coordinate $expected): void
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
            new ActivityRouteLocator($activityStreamRepository)->locateAt(
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
    public function testLocateAtReturnsNullOutsideTheActivity(string $on): void
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

        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAt(
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

    public function testLocateAtReturnsNullWithoutStreams(): void
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

        $this->assertNull(new ActivityRouteLocator($activityStreamRepository)->locateAt(
            activity: $activity,
            on: SerializableDateTime::fromString('2026-06-01 10:00:10'),
        ));
    }
}

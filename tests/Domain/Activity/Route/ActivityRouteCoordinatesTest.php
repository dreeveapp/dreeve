<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Route;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Route\ActivityRouteCoordinates;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class ActivityRouteCoordinatesTest extends ContainerTestCase
{
    private DbalActivityStreamRepository $activityStreamRepository;
    private ActivityRouteCoordinates $routeCoordinates;

    public function testFirstPrefersTheLatLngStream(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('48.0'), Longitude::fromString('2.0')))
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.0, 4.0], [51.1, 4.1]])
            ->build());

        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('51.0'), Longitude::fromString('4.0')),
            $this->routeCoordinates->first($activity)
        );
    }

    public function testFirstFallsBackToTheStartingCoordinateRatherThanThePolyline(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('48.0'), Longitude::fromString('2.0')))
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();

        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('48.0'), Longitude::fromString('2.0')),
            $this->routeCoordinates->first($activity)
        );
    }

    public function testFirstDoesNotFallBackToThePolylineWhenThereIsNoStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();

        $this->assertNull($this->routeCoordinates->first($activity));
    }

    public function testFirstReturnsNullWhenTheActivityCannotBeLocated(): void
    {
        $this->assertNull($this->routeCoordinates->first(ActivityBuilder::fromDefaults()->build()));
    }

    public function testLastPrefersTheLatLngStream(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.0, 4.0], [51.1, 4.1]])
            ->build());

        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('51.1'), Longitude::fromString('4.1')),
            $this->routeCoordinates->last($activity)
        );
    }

    public function testLastFallsBackToThePolyline(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('48.0'), Longitude::fromString('2.0')))
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();

        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('50.0'), Longitude::fromString('3.0')),
            $this->routeCoordinates->last($activity)
        );
    }

    public function testLastDoesNotFallBackToTheStartingCoordinate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('48.0'), Longitude::fromString('2.0')))
            ->build();

        $this->assertNull($this->routeCoordinates->last($activity));
    }

    public function testAllPrefersTheLatLngStream(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.0, 4.0], [51.1, 4.1], [51.2, 4.2]])
            ->build());

        $this->assertSame([[51.0, 4.0], [51.1, 4.1], [51.2, 4.2]], $this->allAsFloats($activity));
    }

    public function testAllFallsBackToThePolyline(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();

        $this->assertSame([[49.0, 2.5], [50.0, 3.0]], $this->allAsFloats($activity));
    }

    public function testAllIsEmptyWhenTheActivityCannotBeLocated(): void
    {
        $this->assertSame([], $this->allAsFloats(ActivityBuilder::fromDefaults()->build()));
    }

    public function testEntriesWithoutAGpsFixAreSkipped(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([null, [51.0, 4.0], [], null, [51.2, 4.2], null])
            ->build());

        $this->assertSame([[51.0, 4.0], [51.2, 4.2]], $this->allAsFloats($activity));
        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('51.0'), Longitude::fromString('4.0')),
            $this->routeCoordinates->first($activity)
        );
        $this->assertEquals(
            Coordinate::createFromLatAndLng(Latitude::fromString('51.2'), Longitude::fromString('4.2')),
            $this->routeCoordinates->last($activity)
        );
    }

    public function testAStreamThatOnlyContainsNullEntriesFallsBackToThePolyline(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[49.0, 2.5], [50.0, 3.0]]))
            ->build();
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId($activity->getId())
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([null, null])
            ->build());

        $this->assertSame([[49.0, 2.5], [50.0, 3.0]], $this->allAsFloats($activity));
    }

    public function testTheStreamOfAnotherActivityIsIgnored(): void
    {
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('other'))
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[51.0, 4.0]])
            ->build());

        $this->assertSame([], $this->allAsFloats(ActivityBuilder::fromDefaults()->build()));
    }

    /**
     * @return array<int, array{float, float}>
     */
    private function allAsFloats(Activity $activity): array
    {
        $coordinates = [];
        foreach ($this->routeCoordinates->all($activity) as $coordinate) {
            $coordinates[] = [$coordinate->getLatitude()->toFloat(), $coordinate->getLongitude()->toFloat()];
        }

        return $coordinates;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityStreamRepository = new DbalActivityStreamRepository($this->getConnection());
        $this->routeCoordinates = new ActivityRouteCoordinates($this->activityStreamRepository);
    }
}

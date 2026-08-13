<?php

namespace App\Tests\Domain\Activity\Route\Signature;

use App\Domain\Activity\Route\Signature\RouteGrid;
use App\Domain\Activity\Route\Signature\RouteWaypoints;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use PHPUnit\Framework\TestCase;

class RouteGridTest extends TestCase
{
    private RouteGrid $routeGrid;

    public function testCellsFor(): void
    {
        $this->assertEquals(
            [50760324000, 50760684002],
            $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates([
                [51.0, 3.0],
                [51.0005, 3.0005],
                [51.001, 3.001],
            ]))->toArray()
        );
    }

    public function testCellsForACoordinateOnAnExactGridBoundary(): void
    {
        $this->assertSame(51031, (int) floor(51.032 / 0.001));

        $this->assertEquals(
            [141032 * 360001 + 183000],
            $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates([[51.032, 3.0]]))->toArray()
        );
    }

    public function testCellsForASparseSegment(): void
    {
        $cells = $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates([
            [51.0, 3.0],
            [51.01, 3.0],
        ]))->toArray();

        $this->assertCount(11, $cells);
        foreach ($cells as $index => $cell) {
            if (0 === $index) {
                continue;
            }
            $this->assertSame(360001, $cell - $cells[$index - 1]);
        }
    }

    public function testCellsForIsIndependentOfDirection(): void
    {
        $coordinates = [[51.0, 3.0], [51.01, 3.02], [51.03, 3.01]];

        $this->assertEquals(
            $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates($coordinates))->toArray(),
            $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates(array_reverse($coordinates)))->toArray()
        );
    }

    public function testCellsForARouteWithoutValidCoordinates(): void
    {
        $cells = $this->routeGrid->cellsFor(EncodedPolyline::fromCoordinates([[200.0, 3.0]]));

        $this->assertTrue($cells->isEmpty());
        $this->assertSame(0, $cells->count());
    }

    public function testWaypointsForAreReversedWhenTheRouteIs(): void
    {
        $coordinates = [[51.0, 3.0], [51.01, 3.02], [51.03, 3.01], [51.05, 3.04]];

        $forward = $this->routeGrid->waypointsFor(EncodedPolyline::fromCoordinates($coordinates))->toArray();
        $backward = $this->routeGrid->waypointsFor(EncodedPolyline::fromCoordinates(array_reverse($coordinates)))->toArray();

        $this->assertCount(2 * RouteGrid::WAYPOINT_COUNT, $forward);

        $reversed = [];
        for ($i = count($backward) - 2; $i >= 0; $i -= 2) {
            $reversed[] = $backward[$i];
            $reversed[] = $backward[$i + 1];
        }
        $this->assertEqualsWithDelta($forward, $reversed, 60);
    }

    public function testWaypointsForARouteWithoutValidCoordinates(): void
    {
        $this->assertTrue(
            $this->routeGrid->waypointsFor(EncodedPolyline::fromCoordinates([[200.0, 3.0]]))->isEmpty()
        );
    }

    public function testMedianDistanceInMeterToIsZeroForAnIdenticalRoute(): void
    {
        $waypoints = $this->routeGrid->waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.05, 3.04]]));

        $this->assertSame(0.0, $waypoints->medianDistanceInMeterTo($waypoints));
    }

    public function testMedianDistanceInMeterToIsInfiniteWhenTheWaypointCountsDiffer(): void
    {
        $waypoints = $this->routeGrid->waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.05, 3.04]]));

        $this->assertSame(INF, $waypoints->medianDistanceInMeterTo(RouteWaypoints::empty()));
    }

    public function testChecksumFor(): void
    {
        $polyline = EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.01, 3.0]]);

        $this->assertSame('3f7fdad6', $this->routeGrid->checksumFor($polyline));
        $this->assertNotSame(
            $this->routeGrid->checksumFor($polyline),
            $this->routeGrid->checksumFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.02, 3.0]]))
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->routeGrid = new RouteGrid();
    }
}

<?php

namespace App\Tests\Domain\Geography\Grid;

use App\Domain\Geography\Grid\RouteGrid;
use App\Domain\Geography\Grid\RouteWaypoints;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use PHPUnit\Framework\TestCase;

class RouteGridTest extends TestCase
{
    public function testCellsFor(): void
    {
        $this->assertEquals(
            [50760324000, 50760684002],
            RouteGrid::cellsFor(EncodedPolyline::fromCoordinates([
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
            RouteGrid::cellsFor(EncodedPolyline::fromCoordinates([[51.032, 3.0]]))->toArray()
        );
    }

    public function testCellsForASparseSegment(): void
    {
        $cells = RouteGrid::cellsFor(EncodedPolyline::fromCoordinates([
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
            RouteGrid::cellsFor(EncodedPolyline::fromCoordinates($coordinates))->toArray(),
            RouteGrid::cellsFor(EncodedPolyline::fromCoordinates(array_reverse($coordinates)))->toArray()
        );
    }

    public function testCellsForARouteWithoutValidCoordinates(): void
    {
        $cells = RouteGrid::cellsFor(EncodedPolyline::fromCoordinates([[200.0, 3.0]]));

        $this->assertTrue($cells->isEmpty());
        $this->assertSame(0, $cells->count());
    }

    public function testWaypointsForAreReversedWhenTheRouteIs(): void
    {
        $coordinates = [[51.0, 3.0], [51.01, 3.02], [51.03, 3.01], [51.05, 3.04]];

        $forward = RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates($coordinates))->toArray();
        $backward = RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates(array_reverse($coordinates)))->toArray();

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
            RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates([[200.0, 3.0]]))->isEmpty()
        );
    }

    public function testWaypointsForARouteThatNeverMoves(): void
    {
        $this->assertTrue(
            RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.0, 3.0]]))->isEmpty()
        );
        $this->assertFalse(
            RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.05, 3.04]]))->isEmpty()
        );
    }

    public function testMaxDistanceInMeterToIsZeroForAnIdenticalRoute(): void
    {
        $waypoints = RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.05, 3.04]]));

        $this->assertSame(0.0, $waypoints->maxDistanceInMeterTo($waypoints));
    }

    public function testMaxDistanceInMeterToIsInfiniteWhenTheWaypointCountsDiffer(): void
    {
        $waypoints = RouteGrid::waypointsFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.05, 3.04]]));

        $this->assertSame(INF, $waypoints->maxDistanceInMeterTo(RouteWaypoints::empty()));
    }

    public function testChecksumFor(): void
    {
        $polyline = EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.01, 3.0]]);

        $this->assertSame('3f7fdad6', RouteGrid::checksumFor($polyline));
        $this->assertNotSame(
            RouteGrid::checksumFor($polyline),
            RouteGrid::checksumFor(EncodedPolyline::fromCoordinates([[51.0, 3.0], [51.02, 3.0]]))
        );
    }
}

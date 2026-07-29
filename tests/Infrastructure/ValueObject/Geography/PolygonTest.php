<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\BoundingBox;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\Geography\PrunedPolygon;
use PHPUnit\Framework\TestCase;

class PolygonTest extends TestCase
{
    private const array SQUARE = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]];
    private const array HOLE = [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0], [4.0, 6.0], [4.0, 4.0]];

    public function testBoundingBox(): void
    {
        $this->assertEquals(
            [0.0, 0.0, 10.0, 10.0],
            Polygon::fromLngLatRings([self::SQUARE])->boundingBox()->jsonSerialize()
        );
    }

    public function testBoundingBoxIgnoresHoles(): void
    {
        $this->assertEquals(
            [0.0, 0.0, 10.0, 10.0],
            Polygon::fromLngLatRings([self::SQUARE, [[-5.0, -5.0], [-4.0, -5.0], [-4.0, -4.0], [-5.0, -5.0]]])
                ->boundingBox()
                ->jsonSerialize()
        );
    }

    public function testBoundingBoxWithoutAnExteriorRing(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Cannot determine the bounding box of a polygon without an exterior ring.'));

        Polygon::fromLngLatRings([])->boundingBox();
    }

    public function testPrunedForRayCastsFromABoxThatMissesThePolygonEntirely(): void
    {
        $this->assertNull(
            Polygon::fromLngLatRings([self::SQUARE])
                ->pruned(BoundingBox::fromArray([0.0, 50.0, 10.0, 60.0]))
        );
    }

    public function testPrunedForRayCastsFromWithoutAnExteriorRing(): void
    {
        $this->assertNull(
            Polygon::fromLngLatRings([])->pruned(BoundingBox::fromArray([0.0, 0.0, 10.0, 10.0]))
        );
    }

    public function testPruningDoesNotChangeTheAnswer(): void
    {
        $polygon = Polygon::fromLngLatRings([self::SQUARE, self::HOLE]);
        $globe = BoundingBox::fromArray([-180.0, -90.0, 180.0, 90.0]);

        for ($longitude = -2.0; $longitude <= 12.0; $longitude += 0.5) {
            for ($latitude = -2.0; $latitude <= 12.0; $latitude += 0.5) {
                $probe = [[$longitude, $latitude]];
                $tight = BoundingBox::fromLngLatPairs($probe);

                $this->assertEquals(
                    $polygon->pruned($globe)?->containsAnyOf($probe) ?? false,
                    $polygon->pruned($tight)?->containsAnyOf($probe) ?? false,
                    sprintf('Pruning changed the answer at [%s, %s]', $longitude, $latitude)
                );
            }
        }
    }

    public function testPrunedForRayCastsFromReturnsAQueryablePolygon(): void
    {
        $this->assertInstanceOf(
            PrunedPolygon::class,
            Polygon::fromLngLatRings([self::SQUARE])
                ->pruned(BoundingBox::fromArray([0.0, 0.0, 10.0, 10.0]))
        );
    }
}

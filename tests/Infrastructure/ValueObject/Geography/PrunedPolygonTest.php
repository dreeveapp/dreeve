<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\BoundingBox;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\Geography\PrunedPolygon;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PrunedPolygonTest extends TestCase
{
    private const array SQUARE = [[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]];
    private const array HOLE = [[4.0, 4.0], [6.0, 4.0], [6.0, 6.0], [4.0, 6.0], [4.0, 4.0]];
    private const array SECOND_HOLE = [[1.0, 1.0], [2.0, 1.0], [2.0, 2.0], [1.0, 2.0], [1.0, 1.0]];

    #[TestWith([5.0, 5.0, true, 'the centre'])]
    #[TestWith([0.5, 0.5, true, 'near a corner'])]
    #[TestWith([-1.0, 5.0, false, 'west of it'])]
    #[TestWith([11.0, 5.0, false, 'east of it'])]
    #[TestWith([5.0, -1.0, false, 'south of it'])]
    #[TestWith([5.0, 11.0, false, 'north of it'])]
    public function testContainsAnyOfASquare(float $longitude, float $latitude, bool $expected, string $description): void
    {
        $this->assertEquals($expected, $this->pruned([self::SQUARE])->containsAnyOf([[$longitude, $latitude]]), $description);
    }

    #[TestWith([5.0, 5.0, false, 'inside the hole'])]
    #[TestWith([2.5, 5.0, true, 'between the hole and the west edge'])]
    #[TestWith([8.0, 5.0, true, 'between the hole and the east edge'])]
    #[TestWith([5.0, 8.0, true, 'north of the hole'])]
    #[TestWith([20.0, 5.0, false, 'outside the polygon entirely'])]
    public function testContainsAnyOfASquareWithAHole(float $longitude, float $latitude, bool $expected, string $description): void
    {
        $this->assertEquals($expected, $this->pruned([self::SQUARE, self::HOLE])->containsAnyOf([[$longitude, $latitude]]), $description);
    }

    #[TestWith([5.0, 5.0, false, 'inside the first hole'])]
    #[TestWith([1.5, 1.5, false, 'inside the second hole'])]
    #[TestWith([8.0, 8.0, true, 'inside neither hole'])]
    public function testContainsAnyOfAPolygonWithSeveralHoles(float $longitude, float $latitude, bool $expected, string $description): void
    {
        $polygon = $this->pruned([self::SQUARE, self::HOLE, self::SECOND_HOLE]);

        $this->assertEquals($expected, $polygon->containsAnyOf([[$longitude, $latitude]]), $description);
    }

    #[TestWith([5.0, 8.0, false, 'in the notch'])]
    #[TestWith([2.0, 5.0, true, 'in the left arm'])]
    #[TestWith([8.0, 5.0, true, 'in the right arm'])]
    #[TestWith([5.0, 2.0, true, 'in the base'])]
    public function testContainsAnyOfAConcaveShape(float $longitude, float $latitude, bool $expected, string $description): void
    {
        $u = [
            [0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [7.0, 10.0],
            [7.0, 4.0], [3.0, 4.0], [3.0, 10.0], [0.0, 10.0], [0.0, 0.0],
        ];

        $this->assertEquals($expected, $this->pruned([$u])->containsAnyOf([[$longitude, $latitude]]), $description);
    }

    public function testContainsAnyOfIsTrueWhenASingleCoordinateMatches(): void
    {
        $this->assertTrue($this->pruned([self::SQUARE])->containsAnyOf([
            [-5.0, -5.0],
            [-4.0, -4.0],
            [5.0, 5.0],
            [50.0, 50.0],
        ]));
    }

    public function testContainsAnyOfIsFalseWhenNoCoordinateMatches(): void
    {
        $this->assertFalse($this->pruned([self::SQUARE])->containsAnyOf([
            [-5.0, -5.0],
            [-4.0, -4.0],
            [50.0, 50.0],
        ]));
    }

    public function testContainsAnyOfWithoutCoordinates(): void
    {
        $this->assertFalse($this->pruned([self::SQUARE])->containsAnyOf([]));
    }

    /**
     * @param list<list<array{float, float}>> $rings
     */
    private function pruned(array $rings): PrunedPolygon
    {
        $pruned = Polygon::fromLngLatRings($rings)
            ->pruned(BoundingBox::fromArray([-180.0, -90.0, 180.0, 90.0]));

        $this->assertNotNull($pruned);

        return $pruned;
    }
}

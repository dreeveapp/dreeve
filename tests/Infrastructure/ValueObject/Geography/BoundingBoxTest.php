<?php

namespace App\Tests\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\Geography\BoundingBox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class BoundingBoxTest extends TestCase
{
    public function testFromLngLatPairsForASinglePair(): void
    {
        $this->assertEquals(
            [4.5, 51.0, 4.5, 51.0],
            BoundingBox::fromLngLatPairs([[4.5, 51.0]])->jsonSerialize()
        );
    }

    public function testFromLngLatPairsSpansEveryPair(): void
    {
        $this->assertEquals(
            [-3.5, 48.25, 7.75, 52.0],
            BoundingBox::fromLngLatPairs([
                [4.5, 51.0],
                [-3.5, 52.0],
                [7.75, 48.25],
                [0.0, 50.0],
            ])->jsonSerialize()
        );
    }

    public function testFromArrayRoundTrip(): void
    {
        $bounds = [-180.0, -90.0, 180.0, 90.0];

        $this->assertEquals($bounds, BoundingBox::fromArray($bounds)->jsonSerialize());
    }

    public function testGetters(): void
    {
        $box = BoundingBox::fromArray([-3.5, 48.25, 7.75, 52.0]);

        $this->assertEquals(-3.5, $box->getMinLng());
        $this->assertEquals(48.25, $box->getMinLat());
        $this->assertEquals(7.75, $box->getMaxLng());
        $this->assertEquals(52.0, $box->getMaxLat());
    }

    #[TestWith([[11.0, 0.0, 20.0, 10.0], false, 'entirely east'])]
    #[TestWith([[-20.0, 0.0, -11.0, 10.0], false, 'entirely west'])]
    #[TestWith([[0.0, 11.0, 10.0, 20.0], false, 'entirely north'])]
    #[TestWith([[0.0, -20.0, 10.0, -11.0], false, 'entirely south'])]
    #[TestWith([[11.0, 11.0, 20.0, 20.0], false, 'diagonally away'])]
    #[TestWith([[2.0, 2.0, 3.0, 3.0], true, 'fully contained'])]
    #[TestWith([[-5.0, -5.0, 15.0, 15.0], true, 'fully containing'])]
    #[TestWith([[5.0, 5.0, 15.0, 15.0], true, 'partially overlapping'])]
    #[TestWith([[0.0, 0.0, 10.0, 10.0], true, 'identical'])]
    public function testOverlaps(array $other, bool $expected, string $description): void
    {
        $box = BoundingBox::fromArray([0.0, 0.0, 10.0, 10.0]);

        $this->assertEquals($expected, $box->overlaps(BoundingBox::fromArray($other)), $description);
        $this->assertEquals($expected, BoundingBox::fromArray($other)->overlaps($box), $description.' (reversed)');
    }

    #[TestWith([[10.0, 0.0, 20.0, 10.0], 'touching on the east edge'])]
    #[TestWith([[-10.0, 0.0, 0.0, 10.0], 'touching on the west edge'])]
    #[TestWith([[0.0, 10.0, 10.0, 20.0], 'touching on the north edge'])]
    #[TestWith([[0.0, -10.0, 10.0, 0.0], 'touching on the south edge'])]
    #[TestWith([[10.0, 10.0, 20.0, 20.0], 'touching on a corner'])]
    public function testTouchingBoxesOverlap(array $other, string $description): void
    {
        $box = BoundingBox::fromArray([0.0, 0.0, 10.0, 10.0]);

        $this->assertTrue($box->overlaps(BoundingBox::fromArray($other)), $description);
        $this->assertTrue(BoundingBox::fromArray($other)->overlaps($box), $description.' (reversed)');
    }

    public function testOverlapsTheFullBand(): void
    {
        $fullBand = BoundingBox::fromArray([-180.0, -90.0, 180.0, 90.0]);

        $this->assertTrue($fullBand->overlaps(BoundingBox::fromArray([4.0, 50.0, 5.0, 51.0])));
        $this->assertTrue(BoundingBox::fromArray([4.0, 50.0, 5.0, 51.0])->overlaps($fullBand));
    }

    public function testWidenedWhenSpanningTheAntimeridian(): void
    {
        $this->assertEquals(
            [-180.0, -17.5, 180.0, -16.5],
            BoundingBox::fromArray([-179.5, -17.5, 179.5, -16.5])
                ->widenedWhenSpanningTheAntimeridian()
                ->jsonSerialize()
        );
    }

    #[TestWith([[-90.0, 0.0, 90.0, 10.0], 'a span of exactly 180 degrees'])]
    #[TestWith([[4.0, 50.0, 5.0, 51.0], 'an ordinary box'])]
    public function testWidenedLeavesOtherBoxesAlone(array $bounds, string $description): void
    {
        $this->assertEquals(
            $bounds,
            BoundingBox::fromArray($bounds)->widenedWhenSpanningTheAntimeridian()->jsonSerialize(),
            $description
        );
    }
}

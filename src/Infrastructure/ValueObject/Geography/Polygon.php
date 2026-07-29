<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

final readonly class Polygon
{
    /**
     * @param list<list<array{float, float}>> $rings
     */
    private function __construct(
        private array $rings,
    ) {
    }

    /**
     * @param list<list<array{float, float}>> $rings
     */
    public static function fromLngLatRings(array $rings): self
    {
        return new self($rings);
    }

    public function boundingBox(): BoundingBox
    {
        $exteriorRing = $this->rings[0] ?? [];
        if ([] === $exteriorRing) {
            throw new \InvalidArgumentException('Cannot determine the bounding box of a polygon without an exterior ring.');
        }

        return BoundingBox::fromLngLatPairs($exteriorRing);
    }

    public function pruned(BoundingBox $bounds): ?PrunedPolygon
    {
        $exteriorEdges = $this->relevantEdges($this->rings[0] ?? [], $bounds);
        if ([] === $exteriorEdges) {
            return null;
        }

        $holeEdges = [];
        foreach (array_slice($this->rings, 1) as $hole) {
            if ([] === $edges = $this->relevantEdges($hole, $bounds)) {
                continue;
            }
            $holeEdges[] = $edges;
        }

        return new PrunedPolygon($exteriorEdges, $holeEdges);
    }

    /**
     * @param list<array{float, float}> $ring
     *
     * @return list<float> flattened as [x1, y1, x2, y2, ...]
     */
    private function relevantEdges(array $ring, BoundingBox $bounds): array
    {
        $minLng = $bounds->getMinLng();
        $minLat = $bounds->getMinLat();
        $maxLat = $bounds->getMaxLat();

        $edges = [];
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ($yi < $minLat && $yj < $minLat) {
                continue;
            }
            if ($yi > $maxLat && $yj > $maxLat) {
                continue;
            }
            if ($xi < $minLng && $xj < $minLng) {
                continue;
            }

            $edges[] = $xi;
            $edges[] = $yi;
            $edges[] = $xj;
            $edges[] = $yj;
        }

        return $edges;
    }
}

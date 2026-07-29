<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

final readonly class PrunedPolygon
{
    /**
     * @param list<float>       $exteriorEdges
     * @param list<list<float>> $holeEdges
     */
    public function __construct(
        private array $exteriorEdges,
        private array $holeEdges,
    ) {
    }

    /**
     * @param list<array{float, float}> $lngLatPairs
     */
    public function containsAnyOf(array $lngLatPairs): bool
    {
        foreach ($lngLatPairs as [$longitude, $latitude]) {
            if (!$this->edgesContain($this->exteriorEdges, $longitude, $latitude)) {
                continue;
            }
            foreach ($this->holeEdges as $hole) {
                if ($this->edgesContain($hole, $longitude, $latitude)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<float> $edges
     */
    private function edgesContain(array $edges, float $x, float $y): bool
    {
        $inside = false;

        for ($i = 0, $count = count($edges); $i < $count; $i += 4) {
            $yi = $edges[$i + 1];
            $yj = $edges[$i + 3];

            if (($yi > $y) === ($yj > $y)) {
                continue;
            }
            $xi = $edges[$i];
            $xj = $edges[$i + 2];

            if ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}

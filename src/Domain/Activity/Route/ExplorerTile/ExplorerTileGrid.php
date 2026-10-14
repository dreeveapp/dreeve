<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

final readonly class ExplorerTileGrid
{
    public const int ZOOM = 14;
    public const int TILES_PER_AXIS = 1 << self::ZOOM;
    public const int MAX_BRIDGEABLE_GAP_IN_TILES = 4;

    private const float MAX_LATITUDE = 85.05112878;

    /**
     * @param array<int, mixed> $latLngCoordinates
     */
    public function tilesFor(array $latLngCoordinates): ExplorerTiles
    {
        $tiles = [];
        $previous = null;
        foreach ($latLngCoordinates as $point) {
            if (!is_array($point) || !isset($point[0], $point[1])) {
                continue;
            }

            $current = $this->project((float) $point[0], (float) $point[1]);
            $tiles[self::tileKey((int) $current[0], (int) $current[1])] = true;

            if (null !== $previous) {
                $this->addCrossedTiles($previous, $current, $tiles);
            }
            $previous = $current;
        }

        return ExplorerTiles::fromFlippedTileKeys($tiles);
    }

    public static function tileKey(int $x, int $y): int
    {
        return $x * self::TILES_PER_AXIS + $y;
    }

    /**
     * @param array{float, float} $from
     * @param array{float, float} $to
     * @param array<int, true>    $tiles
     */
    private function addCrossedTiles(array $from, array $to, array &$tiles): void
    {
        [$fromX, $fromY] = $from;
        [$toX, $toY] = $to;
        $x = (int) $fromX;
        $y = (int) $fromY;
        $stepCountX = abs((int) $toX - $x);
        $stepCountY = abs((int) $toY - $y);

        if (max($stepCountX, $stepCountY) > self::MAX_BRIDGEABLE_GAP_IN_TILES) {
            return;
        }

        $deltaX = $toX - $fromX;
        $deltaY = $toY - $fromY;
        $stepX = $deltaX <=> 0;
        $stepY = $deltaY <=> 0;
        $nextBoundaryX = 0 === $stepX ? INF : (($stepX > 0 ? $x + 1 : $x) - $fromX) / $deltaX;
        $nextBoundaryY = 0 === $stepY ? INF : (($stepY > 0 ? $y + 1 : $y) - $fromY) / $deltaY;

        while ($stepCountX + $stepCountY > 0) {
            if (0 === $stepCountY || (0 !== $stepCountX && $nextBoundaryX < $nextBoundaryY)) {
                $x += $stepX;
                --$stepCountX;
                $nextBoundaryX += abs(1 / $deltaX);
            } else {
                $y += $stepY;
                --$stepCountY;
                $nextBoundaryY += abs(1 / $deltaY);
            }
            $tiles[self::tileKey($x, $y)] = true;
        }
    }

    /**
     * @return array{float, float}
     */
    private function project(float $latitude, float $longitude): array
    {
        $latitude = max(-self::MAX_LATITUDE, min(self::MAX_LATITUDE, $latitude));
        $longitude = max(-180.0, min(180.0, $longitude));
        $maxTileCoordinate = self::TILES_PER_AXIS - 1e-9;

        return [
            min($maxTileCoordinate, ($longitude + 180) / 360 * self::TILES_PER_AXIS),
            min($maxTileCoordinate, (1 - asinh(tan(deg2rad($latitude))) / M_PI) / 2 * self::TILES_PER_AXIS),
        ];
    }
}

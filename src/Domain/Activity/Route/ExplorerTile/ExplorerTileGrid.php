<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\ExplorerTile;

final readonly class ExplorerTileGrid
{
    public const int ZOOM = 14;
    public const int TILES_PER_AXIS = 1 << self::ZOOM;

    private const float MAX_LATITUDE = 85.05112878;

    /**
     * @param array<int, mixed> $latLngCoordinates
     */
    public function tilesFor(array $latLngCoordinates): ExplorerTiles
    {
        $tiles = [];
        foreach ($latLngCoordinates as $point) {
            if (!is_array($point) || !isset($point[0], $point[1])) {
                continue;
            }

            [$x, $y] = $this->project((float) $point[0], (float) $point[1]);
            $tiles[self::tileKey((int) $x, (int) $y)] = true;
        }

        return ExplorerTiles::fromFlippedTileKeys($tiles);
    }

    public static function tileKey(int $x, int $y): int
    {
        return $x * self::TILES_PER_AXIS + $y;
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

<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Config\Leaflet\HeatmapConfig;
use App\Infrastructure\Config\Leaflet\LeafletConfig;

final readonly class MapsSettings
{
    public const string DEFAULT_POLYLINE_COLOR = '#fc6719';
    public const string DEFAULT_TILE_LAYER_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public const int DEFAULT_HEATMAP_INITIAL_ZOOM = 12;

    private function __construct(
        private LeafletConfig $leafletConfig,
        private HeatmapConfig $heatmapConfig,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        /** @var array<string, mixed> $heatmap */
        $heatmap = is_array($data['heatmap'] ?? null) ? $data['heatmap'] : [];
        /** @var array<int, mixed> $initialCenter */
        $initialCenter = is_array($heatmap['initialCenter'] ?? null) ? array_values($heatmap['initialCenter']) : [];
        /** @var array<int, mixed> $tileLayerUrls */
        $tileLayerUrls = is_array($data['tileLayerUrl'] ?? null) ? $data['tileLayerUrl'] : [];

        $leafletConfig = LeafletConfig::create(
            polylineColor: (string) ($data['polylineColor'] ?? self::DEFAULT_POLYLINE_COLOR),
            tileLayerUrls: array_values(array_filter($tileLayerUrls)) ?: [self::DEFAULT_TILE_LAYER_URL],
            enableGreyScale: filter_var($data['enableGreyScale'] ?? true, FILTER_VALIDATE_BOOLEAN),
        );

        return new self(
            leafletConfig: $leafletConfig,
            heatmapConfig: HeatmapConfig::create(
                leafletConfig: $leafletConfig,
                initialCenterLatitude: self::asOptionalString($initialCenter[0] ?? null),
                initialCenterLongitude: self::asOptionalString($initialCenter[1] ?? null),
                initialZoom: self::asOptionalString($heatmap['initialZoom'] ?? null) ?? (string) self::DEFAULT_HEATMAP_INITIAL_ZOOM,
            ),
        );
    }

    public function getLeafletConfig(): LeafletConfig
    {
        return $this->leafletConfig;
    }

    public function getHeatmapConfig(): HeatmapConfig
    {
        return $this->heatmapConfig;
    }

    private static function asOptionalString(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        return '' === trim((string) $value) ? null : (string) $value;
    }
}

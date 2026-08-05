<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\Leaflet;

/**
 * Keyless tile providers offered as one-click choices in the admin panel.
 *
 * A preset may return more than one url — the layers are stacked in order, which is how
 * a satellite basemap gets readable place names on top.
 *
 * Presets requiring an API key are not supported: the app has nowhere to store one for
 * tile requests.
 */
enum BasemapPreset: string
{
    case OPEN_STREET_MAP = 'openStreetMap';
    case ESRI_WORLD_IMAGERY = 'esriWorldImagery';
    case CARTO_POSITRON = 'cartoPositron';
    case CARTO_DARK_MATTER = 'cartoDarkMatter';
    case OPEN_TOPO_MAP = 'openTopoMap';

    /**
     * @return string[]
     */
    public function getTileLayerUrls(): array
    {
        return match ($this) {
            self::OPEN_STREET_MAP => [
                'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            ],
            self::ESRI_WORLD_IMAGERY => [
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}.png',
                'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}.png',
            ],
            self::CARTO_POSITRON => [
                'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
            ],
            self::CARTO_DARK_MATTER => [
                'https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
            ],
            self::OPEN_TOPO_MAP => [
                'https://tile.opentopomap.org/{z}/{x}/{y}.png',
            ],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN_STREET_MAP => 'OpenStreetMap',
            self::ESRI_WORLD_IMAGERY => 'Satellite (Esri World Imagery)',
            self::CARTO_POSITRON => 'Light (CARTO Positron)',
            self::CARTO_DARK_MATTER => 'Dark (CARTO Dark Matter)',
            self::OPEN_TOPO_MAP => 'Topographic (OpenTopoMap)',
        };
    }

    /**
     * @param string[] $tileLayerUrls
     */
    public static function tryFromTileLayerUrls(array $tileLayerUrls): ?self
    {
        $tileLayerUrls = array_values($tileLayerUrls);

        foreach (self::cases() as $preset) {
            if ($preset->getTileLayerUrls() === $tileLayerUrls) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array<string, string[]>
     */
    public static function tileLayerUrlsIndexedByPreset(): array
    {
        $urls = [];
        foreach (self::cases() as $preset) {
            $urls[$preset->value] = $preset->getTileLayerUrls();
        }

        return $urls;
    }
}

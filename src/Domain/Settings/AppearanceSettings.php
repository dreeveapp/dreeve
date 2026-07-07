<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\SportType\SportTypesSortingOrder;
use App\Infrastructure\Config\Leaflet\HeatmapConfig;
use App\Infrastructure\Config\Leaflet\LeafletConfig;
use App\Infrastructure\Config\Photos\HidePhotosForSportTypes;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\Time\Format\DateAndTimeFormat;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;

final readonly class AppearanceSettings
{
    private function __construct(
        private UnitSystem $unitSystem,
        private Locale $locale,
        private DateAndTimeFormat $dateAndTimeFormat,
        private SportTypesSortingOrder $sportTypesSortingOrder,
        private HidePhotosForSportTypes $hidePhotosForSportTypes,
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

        $dateFormat = $data['dateFormat'] ?? [];

        $maps = $data['maps'] ?? [];
        $heatmap = $maps['heatmap'] ?? [];

        $leafletConfig = LeafletConfig::create(
            polylineColor: $maps['polylineColor'] ?? '#fc6719',
            tileLayerUrls: $maps['tileLayerUrl'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            enableGreyScale: $maps['enableGreyScale'] ?? true
        );

        return new self(
            unitSystem: UnitSystem::tryFrom($data['unitSystem'] ?? '') ?? UnitSystem::METRIC,
            locale: Locale::tryFrom($data['locale'] ?? '') ?? Locale::en_US,
            dateAndTimeFormat: DateAndTimeFormat::create(
                dateFormatShort: $dateFormat['short'] ?? 'd-m-y',
                dateFormatNormal: $dateFormat['normal'] ?? 'd-m-Y',
                timeFormat: (int) ($data['timeFormat'] ?? 24)
            ),
            sportTypesSortingOrder: SportTypesSortingOrder::from($data['sportTypesSortingOrder'] ?? []),
            hidePhotosForSportTypes: HidePhotosForSportTypes::from($data['photos']['hidePhotosForSportTypes'] ?? []),
            leafletConfig: $leafletConfig,
            heatmapConfig: HeatmapConfig::create(
                leafletConfig: $leafletConfig,
                initialCenter: [] !== ($heatmap['initialCenter'] ?? []) ? $heatmap['initialCenter'] : null,
                initialZoom: (int) ($heatmap['initialZoom'] ?? 12)
            ),
        );
    }

    public function getUnitSystem(): UnitSystem
    {
        return $this->unitSystem;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getDateAndTimeFormat(): DateAndTimeFormat
    {
        return $this->dateAndTimeFormat;
    }

    public function getSportTypesSortingOrder(): SportTypesSortingOrder
    {
        return $this->sportTypesSortingOrder;
    }

    public function getHidePhotosForSportTypes(): HidePhotosForSportTypes
    {
        return $this->hidePhotosForSportTypes;
    }

    public function getLeafletConfig(): LeafletConfig
    {
        return $this->leafletConfig;
    }

    public function getHeatmapConfig(): HeatmapConfig
    {
        return $this->heatmapConfig;
    }
}

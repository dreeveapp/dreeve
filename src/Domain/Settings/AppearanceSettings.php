<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Application\AppSubTitle;
use App\Domain\Activity\SportType\SportTypesSortingOrder;
use App\Infrastructure\Config\Photos\HidePhotosForSportTypes;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Time\Format\DateAndTimeFormat;

final readonly class AppearanceSettings
{
    private function __construct(
        private ?AppSubTitle $appSubTitle,
        private UnitSystem $unitSystem,
        private Locale $locale,
        private DateAndTimeFormat $dateAndTimeFormat,
        private SportTypesSortingOrder $sportTypesSortingOrder,
        private HidePhotosForSportTypes $hidePhotosForSportTypes,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $dateFormat = $data['dateFormat'] ?? [];

        return new self(
            appSubTitle: AppSubTitle::fromOptionalString($data['appSubTitle'] ?? null),
            unitSystem: UnitSystem::tryFrom($data['unitSystem'] ?? '') ?? UnitSystem::METRIC,
            locale: Locale::tryFrom($data['locale'] ?? '') ?? Locale::en_US,
            dateAndTimeFormat: DateAndTimeFormat::create(
                dateFormatShort: $dateFormat['short'] ?? 'd-m-y',
                dateFormatNormal: $dateFormat['normal'] ?? 'd-m-Y',
                timeFormat: (int) ($data['timeFormat'] ?? 24)
            ),
            sportTypesSortingOrder: SportTypesSortingOrder::from($data['sportTypesSortingOrder'] ?? []),
            hidePhotosForSportTypes: HidePhotosForSportTypes::from($data['photos']['hidePhotosForSportTypes'] ?? []),
        );
    }

    public function getAppSubTitle(): ?AppSubTitle
    {
        return $this->appSubTitle;
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
}

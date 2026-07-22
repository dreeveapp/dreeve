<?php

declare(strict_types=1);

namespace App\Infrastructure\Theme;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\Year;

/**
 * @codeCoverageIgnore
 */
final class Theme
{
    public const string POSITION_TOP = 'top';
    public const string POSITION_BOTTOM = 'bottom';

    /** @var array<string, mixed> */
    private static array $themeConfig;
    private static ?KeyValueStore $keyValueStore = null;

    public static function setKeyValueStore(KeyValueStore $keyValueStore): void
    {
        self::$keyValueStore = $keyValueStore;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getThemeConfig(): array
    {
        if (!self::$keyValueStore instanceof KeyValueStore) {
            throw new \RuntimeException('KeyValueStore not set. Please call Theme::setKeyValueStore() before using this method.');
        }
        if (!isset(self::$themeConfig)) {
            self::$themeConfig = Json::decode((string) self::$keyValueStore->find(Key::THEME));
        }

        return self::$themeConfig;
    }

    /**
     * @return string[]
     */
    public static function defaultChartColors(): array
    {
        return ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'];
    }

    public static function getColorForSportType(SportType $sportType): string
    {
        return self::getThemeConfig()['sportType'][$sportType->value] ?? throw new \RuntimeException(sprintf('color for sportType "%s" not found', $sportType->value));
    }

    public static function getColorForGear(GearId $gearId): string
    {
        return self::getThemeConfig()['gear'][(string) $gearId] ?? throw new \RuntimeException(sprintf('color for gear "%s" not found', $gearId));
    }

    public static function getColorForYear(Year $year): string
    {
        $colors = self::defaultChartColors();
        $yearsAgo = abs(((int) date('Y')) - $year->toInt());

        return $colors[$yearsAgo % count($colors)];
    }
}

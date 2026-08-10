<?php

declare(strict_types=1);

namespace App\Domain\Theme;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\Year;
use Symfony\Contracts\Service\ResetInterface;

final class Theme implements ResetInterface
{
    public const string POSITION_TOP = 'top';
    public const string POSITION_BOTTOM = 'bottom';

    private ?ChartColors $chartColors = null;

    public function __construct(
        private readonly KeyValueStore $keyValueStore,
    ) {
    }

    public function reset(): void
    {
        $this->chartColors = null;
    }

    public function getChartColors(): ChartColors
    {
        if ($this->chartColors instanceof ChartColors) {
            return $this->chartColors;
        }

        try {
            $storedColors = (string) $this->keyValueStore->find(Key::THEME);
        } catch (EntityNotFound) {
            return $this->chartColors = ChartColors::empty();
        }

        return $this->chartColors = ChartColors::fromMap(Json::decode($storedColors ?: '{}'));
    }

    /**
     * @return string[]
     */
    public static function defaultChartColors(): array
    {
        return ChartColors::palette();
    }

    public function getColorForSportType(SportType $sportType): string
    {
        return $this->getChartColors()->forSportType($sportType) ?? self::fallbackColor($sportType->value);
    }

    public function getColorForGear(GearId $gearId): string
    {
        return $this->getChartColors()->forGear($gearId) ?? self::fallbackColor((string) $gearId);
    }

    public static function getColorForYear(Year $year): string
    {
        $colors = self::defaultChartColors();
        $yearsAgo = abs(((int) date('Y')) - $year->toInt());

        return $colors[$yearsAgo % count($colors)];
    }

    private static function fallbackColor(string $key): string
    {
        $colors = self::defaultChartColors();

        return $colors[abs(crc32($key)) % count($colors)];
    }
}

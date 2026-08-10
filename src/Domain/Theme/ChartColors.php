<?php

declare(strict_types=1);

namespace App\Domain\Theme;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Gears;

final readonly class ChartColors
{
    private const string SPORT_TYPE = 'sportType';
    private const string GEAR = 'gear';

    /** @var string[] */
    private const array PALETTE = ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'];

    /**
     * @param array<string, string> $sportTypeColors
     * @param array<string, string> $gearColors
     */
    private function __construct(
        private array $sportTypeColors,
        private array $gearColors,
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * @param array{sportType?: array<string, string>, gear?: array<string, string>} $map
     */
    public static function fromMap(array $map): self
    {
        return new self(
            sportTypeColors: $map[self::SPORT_TYPE] ?? [],
            gearColors: $map[self::GEAR] ?? [],
        );
    }

    /**
     * @return string[]
     */
    public static function palette(): array
    {
        return self::PALETTE;
    }

    public static function for(SportTypes $sportTypes, Gears $usedGears): self
    {
        $sportTypeKeys = [];
        foreach ($sportTypes as $sportType) {
            $sportTypeKeys[] = $sportType->value;
        }

        $gearKeys = [];
        foreach ($usedGears as $gear) {
            $gearKeys[] = (string) $gear->getId();
        }

        return new self(
            sportTypeColors: self::assign($sportTypeKeys),
            gearColors: self::assign($gearKeys),
        );
    }

    public function forSportType(SportType $sportType): ?string
    {
        return $this->sportTypeColors[$sportType->value] ?? null;
    }

    public function forGear(GearId $gearId): ?string
    {
        return $this->gearColors[(string) $gearId] ?? null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function toMap(): array
    {
        return [
            self::SPORT_TYPE => $this->sportTypeColors,
            self::GEAR => $this->gearColors,
        ];
    }

    /**
     * @param string[] $keys
     *
     * @return array<string, string>
     */
    private static function assign(array $keys): array
    {
        $assigned = [];
        foreach (array_values($keys) as $index => $key) {
            $assigned[$key] = self::PALETTE[$index % count(self::PALETTE)];
        }

        return $assigned;
    }
}

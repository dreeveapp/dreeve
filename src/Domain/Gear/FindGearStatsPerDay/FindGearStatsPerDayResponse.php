<?php

declare(strict_types=1);

namespace App\Domain\Gear\FindGearStatsPerDay;

use App\Domain\Gear\GearId;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class FindGearStatsPerDayResponse implements Response
{
    /** @var array<string, mixed> */
    private array $stats = [];

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function addStat(GearId $gearId, SerializableDateTime $date, Kilometer $distance): void
    {
        $this->stats[(string) $gearId][$date->format('Ymd')] = $distance;
    }

    public function getDistanceFor(GearId $gearId, SerializableDateTime $date): ?Kilometer
    {
        return $this->stats[(string) $gearId][$date->format('Ymd')] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStatsPerDay;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class FindYearlyStatsPerDayResponse implements Response
{
    /** @var array<string, array<string|int, array{0: Kilometer, 1: Seconds, 2: Meter}>> */
    private array $stats = [];

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(SerializableDateTime $date, ActivityType $activityType, Kilometer $distance, Seconds $movingTime, Meter $elevation): void
    {
        $this->stats[$activityType->value][$date->format('Ymd')] = [$distance, $movingTime, $elevation];
    }

    public function getDistanceFor(SerializableDateTime $date, ActivityType $activityType): ?Kilometer
    {
        return $this->stats[$activityType->value][$date->format('Ymd')][0] ?? null;
    }

    public function getMovingTimeFor(SerializableDateTime $date, ActivityType $activityType): ?Seconds
    {
        return $this->stats[$activityType->value][$date->format('Ymd')][1] ?? null;
    }

    public function getElevationFor(SerializableDateTime $date, ActivityType $activityType): ?Meter
    {
        return $this->stats[$activityType->value][$date->format('Ymd')][2] ?? null;
    }
}

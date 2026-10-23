<?php

declare(strict_types=1);

namespace App\Domain\Zwift\FindZwiftStatsPerWorld;

use App\Domain\Zwift\ZwiftMap;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;

final readonly class FindZwiftStatsPerWorldResponse implements Response
{
    public function __construct(
        /** @var array<int, array{'zwiftWorld': ZwiftMap, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}> */
        private array $statsPerWorld,
    ) {
    }

    /**
     * @return array<int, array{'zwiftWorld': ZwiftMap, 'numberOfActivities': int, 'distance': Kilometer, 'elevation': Meter, 'movingTime': Seconds, 'calories': int}>
     */
    public function getStatsPerWorld(): array
    {
        return $this->statsPerWorld;
    }
}

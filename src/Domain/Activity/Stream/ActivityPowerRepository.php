<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\ValueObject\Time\DateRange;

interface ActivityPowerRepository
{
    public const array TIME_INTERVALS_IN_SECONDS_REDACTED = [5, 10, 30, 60, 300, 480, 1200, 3600];
    public const array TIME_INTERVALS_IN_SECONDS_ALL = [1, 5, 10, 15, 30, 45, 60, 120, 180, 240, 300, 390, 480, 720, 960, 1200, 1800, 2400, 3000, 3600];

    public function findBestForSportTypes(SportTypes $sportTypes): PowerOutputs;

    public function findBestForSportTypesInDateRange(SportTypes $sportTypes, DateRange $dateRange): PowerOutputs;
}

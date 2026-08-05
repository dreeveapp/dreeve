<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington\FindDistancePerDay;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\CQRS\Query\Query;

/**
 * @implements Query<\App\Domain\Activity\Eddington\FindDistancePerDay\FindDistancePerDayResponse>
 */
final readonly class FindDistancePerDay implements Query
{
    public function __construct(
        private SportTypes $sportTypes,
    ) {
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }
}

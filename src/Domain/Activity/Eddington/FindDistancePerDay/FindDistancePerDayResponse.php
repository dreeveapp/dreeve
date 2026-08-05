<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington\FindDistancePerDay;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;

final readonly class FindDistancePerDayResponse implements Response
{
    public function __construct(
        /** @var array<string, Meter> */
        private array $distancePerDay,
    ) {
    }

    /**
     * @return array<string, Meter>
     */
    public function getDistancePerDay(): array
    {
        return $this->distancePerDay;
    }
}

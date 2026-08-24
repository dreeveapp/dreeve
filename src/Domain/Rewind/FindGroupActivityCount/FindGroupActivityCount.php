<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindGroupActivityCount;

use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\ValueObject\Time\Years;

/**
 * @implements Query<\App\Domain\Rewind\FindGroupActivityCount\FindGroupActivityCountResponse>
 */
final readonly class FindGroupActivityCount implements Query
{
    public function __construct(
        private Years $years,
    ) {
    }

    public function getYears(): Years
    {
        return $this->years;
    }
}

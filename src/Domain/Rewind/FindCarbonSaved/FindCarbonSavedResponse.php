<?php

declare(strict_types=1);

namespace App\Domain\Rewind\FindCarbonSaved;

use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Mass\Kilogram;

final readonly class FindCarbonSavedResponse implements Response
{
    public function __construct(
        private Kilogram $kgCoCarbonSaved,
    ) {
    }

    public function getKgCoCarbonSaved(): Kilogram
    {
        return $this->kgCoCarbonSaved;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement;

final class SimpleUnit implements Unit
{
    use ProvideMeasurementUnit;

    public function getSymbol(): string
    {
        return '';
    }
}

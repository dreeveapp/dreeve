<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement;

trait ProvideMeasurementFormats
{
    public function formatUnitWithSymbol(Unit $unit, int $precision): string
    {
        return sprintf(
            '%s %s',
            $this->formatNumber($unit->toFloat(), $precision),
            $unit->getSymbol()
        );
    }

    public function formatNumber(?float $number, int $precision): string
    {
        if (is_null($number)) {
            return '0';
        }

        $precision = $number < 100 ? $precision : 0;

        return number_format(round($number, $precision), $precision, '.', "\u{00A0}");
    }
}

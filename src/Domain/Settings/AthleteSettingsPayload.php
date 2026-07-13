<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Athlete\InvalidHeartRateFormula;

final readonly class AthleteSettingsPayload
{
    private const string DATE_RANGE_BASED = 'dateRangeBased';
    private const string FIXED = 'fixed';

    /**
     * @param array<string, mixed> $athlete
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $athlete): array
    {
        if (array_key_exists('maxHeartRateFormula', $athlete)) {
            $maxHeartRateFormula = $athlete['maxHeartRateFormula'];
            if (self::DATE_RANGE_BASED === $maxHeartRateFormula) {
                $maxHeartRateFormula = self::toDateRanges(
                    rows: $athlete['maxHeartRateFormulaRanges'] ?? [],
                    label: 'max heart rate formula'
                );
            }
            $athlete['maxHeartRateFormula'] = $maxHeartRateFormula;
        }

        if (array_key_exists('restingHeartRateFormula', $athlete)) {
            $restingHeartRateFormula = $athlete['restingHeartRateFormula'];
            if (self::DATE_RANGE_BASED === $restingHeartRateFormula) {
                $restingHeartRateFormula = self::toDateRanges(
                    rows: $athlete['restingHeartRateFormulaRanges'] ?? [],
                    label: 'resting heart rate formula'
                );
            } elseif (self::FIXED === $restingHeartRateFormula) {
                $restingHeartRateFormula = self::toHeartRate(
                    value: $athlete['restingHeartRateFormulaFixedValue'] ?? null,
                    label: 'resting heart rate formula'
                );
            }
            $athlete['restingHeartRateFormula'] = $restingHeartRateFormula;
        }

        unset(
            $athlete['maxHeartRateFormulaRanges'],
            $athlete['restingHeartRateFormulaRanges'],
            $athlete['restingHeartRateFormulaFixedValue'],
        );

        return $athlete;
    }

    /**
     * @return array<string, int>
     */
    private static function toDateRanges(mixed $rows, string $label): array
    {
        if (!is_array($rows)) {
            throw new InvalidHeartRateFormula(sprintf('Invalid date ranges provided for the %s', $label));
        }

        $ranges = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidHeartRateFormula(sprintf('Invalid date ranges provided for the %s', $label));
            }

            $on = $row['on'] ?? null;
            if (!is_string($on) || '' === trim($on)) {
                throw new InvalidHeartRateFormula(sprintf('Every date range of the %s needs a date', $label));
            }
            $on = trim($on);
            if (isset($ranges[$on])) {
                throw new InvalidHeartRateFormula(sprintf('The %s cannot contain the same date more than once', $label));
            }

            $ranges[$on] = self::toHeartRate(
                value: $row['bpm'] ?? null,
                label: $label
            );
        }

        return $ranges;
    }

    private static function toHeartRate(mixed $value, string $label): int
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (!is_numeric($value) || (int) $value != $value || (int) $value <= 0) {
            throw new InvalidHeartRateFormula(sprintf('The %s needs a heart rate greater than zero', $label));
        }

        return (int) $value;
    }
}

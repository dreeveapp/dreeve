<?php

declare(strict_types=1);

namespace App\Domain\Activity;

final readonly class Math
{
    /**
     * @param array<int|float> $values
     */
    public static function median(array $values): float
    {
        if ([] === $values) {
            throw new \InvalidArgumentException('Cannot calculate median of empty array.');
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);
        $middle = intdiv($count, 2);

        if (1 === $count % 2) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    /**
     * @param array<int|float> $values
     *
     * @return array<int|float>
     */
    public static function movingAverage(array $values, int $windowSize): array
    {
        $count = count($values);
        if (0 === $count || $windowSize < 1) {
            return $values;
        }
        $half = intdiv($windowSize, 2);

        $smoothed = [];
        for ($i = 0; $i < $count; ++$i) {
            $start = max(0, $i - $half);
            $end = min($count - 1, $i + $half);
            $sum = 0.0;
            for ($j = $start; $j <= $end; ++$j) {
                $sum += $values[$j];
            }
            $smoothed[] = $sum / ($end - $start + 1);
        }

        return $smoothed;
    }

    public static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<int|float|null> $values
     */
    public static function average(array $values): ?int
    {
        $numbers = array_filter($values, static fn (mixed $v): bool => null !== $v);
        if ([] === $numbers) {
            return null;
        }

        return (int) round(array_sum($numbers) / count($numbers));
    }

    /**
     * @param array<int|float|null> $values
     */
    public static function max(array $values): ?int
    {
        $numbers = array_filter($values, static fn (mixed $v): bool => null !== $v);
        if ([] === $numbers) {
            return null;
        }

        return (int) max($numbers);
    }
}

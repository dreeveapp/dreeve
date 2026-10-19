<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

final class FitNikeSessionFix
{
    private const float KM_PER_HOUR_FACTOR = 3.6;
    private const float STEPS_PER_STRIDE = 2.0;
    private const float TOLERANCE = 0.1;
    private const array SPEED_FIELDS = ['avg_speed', 'enhanced_avg_speed', 'max_speed', 'enhanced_max_speed'];
    private const array CADENCE_FIELDS = ['avg_cadence', 'max_cadence'];

    /**
     * @param array<string, mixed> $session
     *
     * @return array<string, mixed>
     */
    public static function apply(array $session, ?int $manufacturerId): array
    {
        if (FitManufacturer::NIKE !== $manufacturerId) {
            return $session;
        }

        $totalTimerTime = is_numeric($session['total_timer_time'] ?? null) ? (float) $session['total_timer_time'] : 0.0;
        if ($totalTimerTime <= 0.0) {
            return $session;
        }

        $averageSpeed = $session['enhanced_avg_speed'] ?? $session['avg_speed'] ?? null;
        if (is_numeric($averageSpeed) && is_numeric($session['total_distance'] ?? null)
            && self::isMultipleOf((float) $averageSpeed, (float) $session['total_distance'] / $totalTimerTime, self::KM_PER_HOUR_FACTOR)) {
            foreach (self::SPEED_FIELDS as $field) {
                if (is_numeric($session[$field] ?? null)) {
                    $session[$field] = (float) $session[$field] / self::KM_PER_HOUR_FACTOR;
                }
            }
        }

        if (is_numeric($session['avg_cadence'] ?? null) && is_numeric($session['total_cycles'] ?? null)
            && self::isMultipleOf((float) $session['avg_cadence'], (float) $session['total_cycles'] / ($totalTimerTime / 60), self::STEPS_PER_STRIDE)) {
            foreach (self::CADENCE_FIELDS as $field) {
                if (is_numeric($session[$field] ?? null)) {
                    $session[$field] = (float) $session[$field] / self::STEPS_PER_STRIDE;
                }
            }
        }

        return $session;
    }

    private static function isMultipleOf(float $value, float $reference, float $factor): bool
    {
        if ($reference <= 0.0) {
            return false;
        }

        return abs($value / $reference - $factor) <= $factor * self::TOLERANCE;
    }
}

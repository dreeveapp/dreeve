<?php

declare(strict_types=1);

namespace App\Domain\Activity\Lap;

final readonly class ActivityLapPowerCalculator
{
    /**
     * Per lap power is not persisted in the database, so it is derived on the fly
     * from the activity's cumulative distance and watts streams. Each lap is matched
     * to the samples that fall within its distance range, based on the lap distances.
     *
     * @param array<int, mixed> $distanceStream cumulative distance in meters per sample
     * @param array<int, mixed> $wattsStream    power in watts per sample, null when missing
     */
    public static function calculate(
        ActivityLaps $laps,
        array $distanceStream,
        array $wattsStream,
    ): ActivityLapPowers {
        $distanceStream = array_values($distanceStream);
        $wattsStream = array_values($wattsStream);

        $lapPowers = ActivityLapPowers::empty();
        if ([] === $distanceStream || [] === $wattsStream) {
            return $lapPowers;
        }

        $numberOfSamples = count($distanceStream);
        $sampleIndex = 0;
        $cumulativeDistance = 0.0;

        foreach ($laps as $lap) {
            $lapEndDistance = $cumulativeDistance + $lap->getDistance()->toFloat();

            $watts = [];
            while ($sampleIndex < $numberOfSamples && (float) $distanceStream[$sampleIndex] < $lapEndDistance) {
                $distance = $distanceStream[$sampleIndex];
                $sampleWatts = $wattsStream[$sampleIndex] ?? null;
                if (null !== $distance && null !== $sampleWatts) {
                    $watts[] = (float) $sampleWatts;
                }
                ++$sampleIndex;
            }

            $lapPowers->add(ActivityLapPower::create(
                lapId: $lap->getLapId(),
                averageWatts: [] === $watts ? null : (int) round(array_sum($watts) / count($watts)),
                maxWatts: [] === $watts ? null : (int) round(max($watts)),
            ));

            $cumulativeDistance = $lapEndDistance;
        }

        return $lapPowers;
    }
}

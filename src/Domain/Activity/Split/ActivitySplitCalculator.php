<?php

declare(strict_types=1);

namespace App\Domain\Activity\Split;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;

/**
 * @phpstan-type SplitSample array{
 *     distance: float,
 *     time: float,
 *     altitude: float|null,
 *     movingSeconds: float
 * }
 * @phpstan-type CalculatedSplit array{
 *     distance: float,
 *     elapsedTimeInSeconds: int,
 *     movingTimeInSeconds: int,
 *     elevationDifference: float,
 *     averageSpeed: float,
 *     isFullLength: bool
 * }
 */
final readonly class ActivitySplitCalculator
{
    private const int MAX_RECORDING_GAP_IN_SECONDS = 60;
    private const float MIN_TRAILING_SPLIT_DISTANCE_IN_METERS = 1.0;

    public function calculate(ActivityStreams $streams, ActivityId $activityId, UnitSystem $unitSystem): ActivitySplits
    {
        $splitLengthInMeters = $unitSystem->distance(1.0)->toMeter()->toFloat();
        $samples = $this->buildSamples($streams);

        if (count($samples) < 2) {
            return ActivitySplits::empty();
        }
        if ($samples[count($samples) - 1]['distance'] - $samples[0]['distance'] < $splitLengthInMeters) {
            return ActivitySplits::empty();
        }

        return $this->toActivitySplits(
            calculatedSplits: $this->calculateSplits($samples, $splitLengthInMeters),
            activityId: $activityId,
            unitSystem: $unitSystem,
        );
    }

    /**
     * @return list<SplitSample>
     */
    private function buildSamples(ActivityStreams $streams): array
    {
        $distances = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        $times = array_values($streams->filterOnType(StreamType::TIME)?->getData() ?? []);
        $altitudes = array_values($streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? []);
        $movingFlags = array_values($streams->filterOnType(StreamType::MOVING)?->getData() ?? []);
        $hasMovingStream = [] !== $movingFlags;

        $samples = [];
        $maxDistance = null;
        $previousTime = null;
        $sampleCount = min(count($distances), count($times));

        for ($i = 0; $i < $sampleCount; ++$i) {
            if (!is_numeric($distances[$i]) || !is_numeric($times[$i])) {
                continue;
            }

            $distance = (float) $distances[$i];
            $time = (float) $times[$i];
            $maxDistance = null === $maxDistance ? $distance : max($maxDistance, $distance);
            $altitude = $altitudes[$i] ?? null;

            $samples[] = [
                'distance' => $maxDistance,
                'time' => $time,
                'altitude' => is_numeric($altitude) ? (float) $altitude : null,
                'movingSeconds' => $this->movingSeconds(
                    elapsedSeconds: null === $previousTime ? 0.0 : $time - $previousTime,
                    isMoving: $hasMovingStream ? (bool) ($movingFlags[$i] ?? true) : null,
                ),
            ];
            $previousTime = $time;
        }

        return $samples;
    }

    private function movingSeconds(float $elapsedSeconds, ?bool $isMoving): float
    {
        if ($elapsedSeconds <= 0.0) {
            return 0.0;
        }
        if (null !== $isMoving) {
            return $isMoving ? $elapsedSeconds : 0.0;
        }

        return $elapsedSeconds <= self::MAX_RECORDING_GAP_IN_SECONDS ? $elapsedSeconds : 0.0;
    }

    /**
     * @param list<SplitSample> $samples
     *
     * @return list<CalculatedSplit>
     */
    private function calculateSplits(array $samples, float $splitLengthInMeters): array
    {
        $splits = [];
        $sampleCount = count($samples);
        $startTime = $samples[0]['time'];
        $startAltitude = $samples[0]['altitude'];
        $splitStartDistance = $samples[0]['distance'];
        $targetDistance = $splitStartDistance + $splitLengthInMeters;
        $movingSeconds = 0.0;

        for ($i = 1; $i < $sampleCount; ++$i) {
            $previous = $samples[$i - 1];
            $current = $samples[$i];
            $deltaDistance = $current['distance'] - $previous['distance'];
            $deltaTime = $current['time'] - $previous['time'];
            $intervalMovingSeconds = $current['movingSeconds'];
            $consumedFraction = 0.0;

            while ($deltaDistance > 0.0 && $current['distance'] >= $targetDistance) {
                $fraction = ($targetDistance - $previous['distance']) / $deltaDistance;
                $crossingTime = $previous['time'] + $fraction * $deltaTime;
                $crossingAltitude = $this->interpolateAltitude($previous['altitude'], $current['altitude'], $fraction);
                $movingSeconds += $intervalMovingSeconds * ($fraction - $consumedFraction);

                $splits[] = $this->buildSplit(
                    distance: $splitLengthInMeters,
                    elapsedSeconds: $crossingTime - $startTime,
                    movingSeconds: $movingSeconds,
                    elevationDifference: $this->elevationDifference($startAltitude, $crossingAltitude),
                    isFullLength: true,
                );

                $consumedFraction = $fraction;
                $startTime = $crossingTime;
                $startAltitude = $crossingAltitude;
                $splitStartDistance = $targetDistance;
                $targetDistance += $splitLengthInMeters;
                $movingSeconds = 0.0;
            }

            $movingSeconds += $intervalMovingSeconds * (1.0 - $consumedFraction);
        }

        $lastSample = $samples[$sampleCount - 1];
        $trailingDistance = $lastSample['distance'] - $splitStartDistance;
        if ($trailingDistance >= self::MIN_TRAILING_SPLIT_DISTANCE_IN_METERS) {
            $splits[] = $this->buildSplit(
                distance: $trailingDistance,
                elapsedSeconds: $lastSample['time'] - $startTime,
                movingSeconds: $movingSeconds,
                elevationDifference: $this->elevationDifference($startAltitude, $lastSample['altitude']),
                isFullLength: false,
            );
        }

        return $splits;
    }

    private function interpolateAltitude(?float $from, ?float $to, float $fraction): ?float
    {
        if (null === $from || null === $to) {
            return $to ?? $from;
        }

        return $from + $fraction * ($to - $from);
    }

    private function elevationDifference(?float $from, ?float $to): float
    {
        if (null === $from || null === $to) {
            return 0.0;
        }

        return $to - $from;
    }

    /**
     * @return CalculatedSplit
     */
    private function buildSplit(
        float $distance,
        float $elapsedSeconds,
        float $movingSeconds,
        float $elevationDifference,
        bool $isFullLength,
    ): array {
        $elapsedTimeInSeconds = max(0, (int) round($elapsedSeconds));
        $movingTimeInSeconds = min($elapsedTimeInSeconds, max(0, (int) round($movingSeconds)));
        $timeForAverageSpeed = $movingTimeInSeconds > 0 ? $movingTimeInSeconds : $elapsedTimeInSeconds;

        return [
            'distance' => round($distance, 2),
            'elapsedTimeInSeconds' => $elapsedTimeInSeconds,
            'movingTimeInSeconds' => $movingTimeInSeconds,
            'elevationDifference' => round($elevationDifference, 1),
            'averageSpeed' => $timeForAverageSpeed > 0 ? round($distance / $timeForAverageSpeed, 3) : 0.0,
            'isFullLength' => $isFullLength,
        ];
    }

    /**
     * @param list<CalculatedSplit> $calculatedSplits
     */
    private function toActivitySplits(array $calculatedSplits, ActivityId $activityId, UnitSystem $unitSystem): ActivitySplits
    {
        $slowestFullSplit = null;
        $fastestFullSplit = null;
        foreach ($calculatedSplits as $calculatedSplit) {
            if (!$calculatedSplit['isFullLength']) {
                continue;
            }
            $slowestFullSplit = null === $slowestFullSplit ? $calculatedSplit['averageSpeed'] : min($slowestFullSplit, $calculatedSplit['averageSpeed']);
            $fastestFullSplit = null === $fastestFullSplit ? $calculatedSplit['averageSpeed'] : max($fastestFullSplit, $calculatedSplit['averageSpeed']);
        }

        $minAverageSpeed = MetersPerSecond::fromOptional($slowestFullSplit);
        $maxAverageSpeed = MetersPerSecond::fromOptional($fastestFullSplit);

        $splits = ActivitySplits::empty();
        foreach ($calculatedSplits as $index => $calculatedSplit) {
            $splits->add(ActivitySplit::create(
                activityId: $activityId,
                unitSystem: $unitSystem,
                splitNumber: $index + 1,
                distance: Meter::from($calculatedSplit['distance']),
                elapsedTimeInSeconds: $calculatedSplit['elapsedTimeInSeconds'],
                movingTimeInSeconds: $calculatedSplit['movingTimeInSeconds'],
                elevationDifference: Meter::from($calculatedSplit['elevationDifference']),
                averageSpeed: MetersPerSecond::from($calculatedSplit['averageSpeed']),
                minAverageSpeed: $minAverageSpeed,
                maxAverageSpeed: $maxAverageSpeed,
                paceZone: 0,
            ));
        }

        return $splits;
    }
}

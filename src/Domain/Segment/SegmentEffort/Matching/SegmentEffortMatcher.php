<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort\Matching;

use App\Domain\Segment\Segment;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\GeoMath;
use App\Infrastructure\ValueObject\Geography\Polyline;

final readonly class SegmentEffortMatcher
{
    private const float START_END_RADIUS_IN_METERS = 35.0;
    private const float CHECKPOINT_RADIUS_IN_METERS = 40.0;
    private const float CHECKPOINT_STEP_IN_DEGREES = 0.0002;
    private const float MIN_DISTANCE_RATIO = 0.5;
    private const float MAX_DISTANCE_RATIO = 1.3;
    private const float STATIONARY_THRESHOLD_IN_METERS = 1.0;

    /**
     * @param array<int, array{float, float}> $latLng
     * @param array<int, int|float>           $time
     * @param array<int, int|float>           $distance
     * @param array<int, int|float|null>      $watts
     * @param array<int, int|float|null>      $heartRate
     *
     * @return list<MatchedEffort>
     */
    public function match(
        Segment $segment,
        array $latLng,
        array $time,
        array $distance,
        array $watts = [],
        array $heartRate = [],
    ): array {
        if (!($polyline = $segment->getPolyline()) instanceof EncodedPolyline) {
            return [];
        }

        $checkpoints = Polyline::fromEncodedPolyline($polyline)
            ->densify(self::CHECKPOINT_STEP_IN_DEGREES)
            ->getLatLngCoordinates();
        if (count($checkpoints) < 2) {
            return [];
        }

        $latLng = array_values($latLng);
        $time = array_values($time);
        $distance = array_values($distance);
        $numberOfPoints = min(count($latLng), count($time), count($distance));
        if ($numberOfPoints < 2) {
            return [];
        }

        $segmentStart = $checkpoints[0];
        $segmentEnd = $checkpoints[count($checkpoints) - 1];
        $segmentLength = $segment->getDistance()->toMeter()->toFloat();

        $candidates = [];
        foreach ($this->findPassesNear($segmentStart, $latLng, $numberOfPoints) as $startIndex) {
            while ($startIndex + 1 < $numberOfPoints && $distance[$startIndex + 1] - $distance[$startIndex] < self::STATIONARY_THRESHOLD_IN_METERS) {
                ++$startIndex;
            }

            foreach ($this->findEndIndexes($segmentEnd, $segmentLength, $startIndex, $latLng, $distance, $numberOfPoints) as $endIndex) {
                if ($this->followsCheckpoints($checkpoints, $latLng, $startIndex, $endIndex)) {
                    $candidates[] = [$startIndex, $endIndex];
                    break;
                }
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$a[1], $b[0]] <=> [$b[1], $a[0]]);

        $matchedEfforts = [];
        $previousEndIndex = -1;
        foreach ($candidates as [$startIndex, $endIndex]) {
            if ($startIndex < $previousEndIndex) {
                continue;
            }

            $matchedEfforts[] = new MatchedEffort(
                startIndex: $startIndex,
                elapsedTimeInSeconds: (float) ($time[$endIndex] - $time[$startIndex]),
                averageWatts: ($averageWatts = $this->average($watts, $startIndex, $endIndex)) !== null ? round($averageWatts, 1) : null,
                averageHeartRate: ($averageHeartRate = $this->average($heartRate, $startIndex, $endIndex)) !== null ? (int) round($averageHeartRate) : null,
                maxHeartRate: ($maxHeartRate = $this->max($heartRate, $startIndex, $endIndex)) !== null ? (int) round($maxHeartRate) : null,
            );
            $previousEndIndex = $endIndex;
        }

        return $matchedEfforts;
    }

    /**
     * @param array{float, float}             $target
     * @param array<int, array{float, float}> $latLng
     *
     * @return list<int>
     */
    private function findPassesNear(array $target, array $latLng, int $numberOfPoints): array
    {
        $passes = [];
        $closestIndex = null;
        $closestDistance = INF;
        for ($index = 0; $index < $numberOfPoints; ++$index) {
            $distanceToTarget = GeoMath::haversineDistance($latLng[$index][0], $latLng[$index][1], $target[0], $target[1]);
            if ($distanceToTarget <= self::START_END_RADIUS_IN_METERS) {
                if ($distanceToTarget < $closestDistance) {
                    $closestIndex = $index;
                    $closestDistance = $distanceToTarget;
                }
                continue;
            }
            if (null !== $closestIndex) {
                $passes[] = $closestIndex;
                $closestIndex = null;
                $closestDistance = INF;
            }
        }
        if (null !== $closestIndex) {
            $passes[] = $closestIndex;
        }

        return $passes;
    }

    /**
     * @param array{float, float}             $segmentEnd
     * @param array<int, array{float, float}> $latLng
     * @param array<int, int|float>           $distance
     *
     * @return list<int>
     */
    private function findEndIndexes(array $segmentEnd, float $segmentLength, int $startIndex, array $latLng, array $distance, int $numberOfPoints): array
    {
        $endIndexes = [];
        $closestIndex = null;
        $closestDistance = INF;
        for ($index = $startIndex + 1; $index < $numberOfPoints; ++$index) {
            $covered = $distance[$index] - $distance[$startIndex];
            if ($covered > $segmentLength * self::MAX_DISTANCE_RATIO) {
                break;
            }

            $distanceToEnd = GeoMath::haversineDistance($latLng[$index][0], $latLng[$index][1], $segmentEnd[0], $segmentEnd[1]);
            if ($distanceToEnd <= self::START_END_RADIUS_IN_METERS && $covered >= $segmentLength * self::MIN_DISTANCE_RATIO) {
                if ($distanceToEnd < $closestDistance) {
                    $closestIndex = $index;
                    $closestDistance = $distanceToEnd;
                }
                continue;
            }
            if (null !== $closestIndex) {
                $endIndexes[] = $this->firstArrival($closestIndex, $startIndex, $distance);
                $closestIndex = null;
                $closestDistance = INF;
            }
        }
        if (null !== $closestIndex) {
            $endIndexes[] = $this->firstArrival($closestIndex, $startIndex, $distance);
        }

        return $endIndexes;
    }

    /**
     * @param array<int, int|float> $distance
     */
    private function firstArrival(int $endIndex, int $startIndex, array $distance): int
    {
        while ($endIndex - 1 > $startIndex && $distance[$endIndex] - $distance[$endIndex - 1] < self::STATIONARY_THRESHOLD_IN_METERS) {
            --$endIndex;
        }

        return $endIndex;
    }

    /**
     * @param list<array{float, float}>       $checkpoints
     * @param array<int, array{float, float}> $latLng
     */
    private function followsCheckpoints(array $checkpoints, array $latLng, int $startIndex, int $endIndex): bool
    {
        $index = $startIndex;
        foreach ($checkpoints as $checkpoint) {
            while ($this->distanceToTrack($checkpoint, $latLng[$index], $latLng[min($index + 1, $endIndex)]) > self::CHECKPOINT_RADIUS_IN_METERS) {
                if (++$index >= $endIndex) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array{float, float} $point
     * @param array{float, float} $trackFrom
     * @param array{float, float} $trackTo
     */
    private function distanceToTrack(array $point, array $trackFrom, array $trackTo): float
    {
        $metersPerDegreeLatitude = 111_320.0;
        $metersPerDegreeLongitude = $metersPerDegreeLatitude * cos(deg2rad($point[0]));

        $fromX = ($trackFrom[1] - $point[1]) * $metersPerDegreeLongitude;
        $fromY = ($trackFrom[0] - $point[0]) * $metersPerDegreeLatitude;
        $toX = ($trackTo[1] - $point[1]) * $metersPerDegreeLongitude;
        $toY = ($trackTo[0] - $point[0]) * $metersPerDegreeLatitude;

        $deltaX = $toX - $fromX;
        $deltaY = $toY - $fromY;
        $lengthSquared = $deltaX ** 2 + $deltaY ** 2;
        $projection = $lengthSquared > 0.0 ? max(0.0, min(1.0, -($fromX * $deltaX + $fromY * $deltaY) / $lengthSquared)) : 0.0;

        return hypot($fromX + $projection * $deltaX, $fromY + $projection * $deltaY);
    }

    /**
     * @param array<int, int|float|null> $values
     */
    private function average(array $values, int $startIndex, int $endIndex): ?float
    {
        $slice = array_filter(
            array_slice(array_values($values), $startIndex, $endIndex - $startIndex + 1),
            static fn (int|float|null $value): bool => null !== $value,
        );
        if ([] === $slice) {
            return null;
        }

        return array_sum($slice) / count($slice);
    }

    /**
     * @param array<int, int|float|null> $values
     */
    private function max(array $values, int $startIndex, int $endIndex): int|float|null
    {
        $slice = array_filter(
            array_slice(array_values($values), $startIndex, $endIndex - $startIndex + 1),
            static fn (int|float|null $value): bool => null !== $value,
        );
        if ([] === $slice) {
            return null;
        }

        return max($slice);
    }
}

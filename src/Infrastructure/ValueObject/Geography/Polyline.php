<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

final readonly class Polyline
{
    /**
     * @param list<array{float, float}> $coordinates
     */
    private function __construct(
        private array $coordinates,
    ) {
    }

    /**
     * @param list<array{float, float}> $coordinates
     */
    public static function fromLatLngCoordinates(array $coordinates): self
    {
        return new self($coordinates);
    }

    public static function fromEncodedPolyline(EncodedPolyline $polyline): self
    {
        return new self(array_values($polyline->decodeAndPairLatLng()));
    }

    /**
     * @return list<array{float, float}>
     */
    public function getLatLngCoordinates(): array
    {
        return $this->coordinates;
    }

    /**
     * The tolerance is expressed in degrees, the same unit as the coordinates
     * (0.0001° ≈ 11m on the ground).
     */
    public function simplify(float $tolerance = 0.0001): self
    {
        $points = $this->coordinates;

        if (count($points) < 2) {
            return new self($points);
        }

        $points = $this->simplifyDouglasPeucker(
            coordinates: $points,
            sqTolerance: $tolerance ** 2
        );

        return new self($points);
    }

    public function sanitize(): self
    {
        $sanitized = [];
        foreach ($this->coordinates as [$latitude, $longitude]) {
            if (!is_finite($latitude)) {
                continue;
            }
            if (!is_finite($longitude)) {
                continue;
            }
            if (abs($latitude) > 90) {
                continue;
            }
            if (abs($longitude) > 180) {
                $longitude = fmod($longitude + 180, 360) - 180;
            }
            $sanitized[] = [$latitude, $longitude];
        }

        return new self($sanitized);
    }

    public function densify(float $maxStepInDegrees = 0.002, int $maxCoordinates = 25000): self
    {
        if (count($this->coordinates) < 2) {
            return $this;
        }

        $length = 0.0;
        foreach ($this->coordinates as $index => [$latitude, $longitude]) {
            if (0 === $index) {
                continue;
            }
            [$previousLatitude, $previousLongitude] = $this->coordinates[$index - 1];
            $length += hypot($this->shortestLongitudeDelta($previousLongitude, $longitude), $latitude - $previousLatitude);
        }

        $step = max($maxStepInDegrees, $length / $maxCoordinates);

        $densified = [$this->coordinates[0]];
        foreach ($this->coordinates as $index => [$latitude, $longitude]) {
            if (0 === $index) {
                continue;
            }
            [$previousLatitude, $previousLongitude] = $this->coordinates[$index - 1];
            $deltaLongitude = $this->shortestLongitudeDelta($previousLongitude, $longitude);
            $deltaLatitude = $latitude - $previousLatitude;

            $steps = (int) ceil(hypot($deltaLongitude, $deltaLatitude) / $step);
            for ($i = 1; $i < $steps; ++$i) {
                $densified[] = [
                    $previousLatitude + $deltaLatitude * $i / $steps,
                    $this->wrapLongitude($previousLongitude + $deltaLongitude * $i / $steps),
                ];
            }
            $densified[] = [$latitude, $longitude];
        }

        return new self($densified);
    }

    public function encode(): EncodedPolyline
    {
        return EncodedPolyline::fromCoordinates($this->coordinates);
    }

    private function shortestLongitudeDelta(float $from, float $to): float
    {
        $delta = $to - $from;

        if ($delta > 180) {
            return $delta - 360;
        }
        if ($delta < -180) {
            return $delta + 360;
        }

        return $delta;
    }

    private function wrapLongitude(float $longitude): float
    {
        if ($longitude > 180) {
            return $longitude - 360;
        }
        if ($longitude < -180) {
            return $longitude + 360;
        }

        return $longitude;
    }

    /**
     * @param list<array{float, float}> $coordinates
     *
     * @return list<array{float, float}>
     */
    private function simplifyDouglasPeucker(array $coordinates, float $sqTolerance): array
    {
        $len = count($coordinates);

        $markers = array_fill(0, $len - 1, null);
        $first = 0;
        $last = $len - 1;

        $firstStack = [];
        $lastStack = [];
        $newPoints = [];

        $markers[$first] = $markers[$last] = 1;

        while (null !== $first && null !== $last) {
            $maxSqDist = 0;
            $index = null;

            for ($i = $first + 1; $i < $last; ++$i) {
                $sqDist = $this->squareSegmentDistance(
                    p: $coordinates[$i],
                    p1: $coordinates[$first],
                    p2: $coordinates[$last]
                );

                if ($sqDist > $maxSqDist) {
                    $index = $i;
                    $maxSqDist = $sqDist;
                }
            }

            if (null !== $index && $maxSqDist > $sqTolerance) {
                $markers[$index] = 1;

                $firstStack[] = $first;
                $lastStack[] = $index;

                $firstStack[] = $index;
                $lastStack[] = $last;
            }

            $first = array_pop($firstStack);
            $last = array_pop($lastStack);
        }

        for ($i = 0; $i < $len; ++$i) {
            if ($markers[$i]) {
                $newPoints[] = $coordinates[$i];
            }
        }

        return $newPoints;
    }

    /**
     * @param array{float, float} $p
     * @param array{float, float} $p1
     * @param array{float, float} $p2
     */
    private function squareSegmentDistance(array $p, array $p1, array $p2): float
    {
        $x = $p1[1]; // longitude
        $y = $p1[0]; // latitude

        $dx = $p2[1] - $x;
        $dy = $p2[0] - $y;

        if (0.0 !== $dx || 0.0 !== $dy) {
            $t = (($p[1] - $x) * $dx + ($p[0] - $y) * $dy) / ($dx * $dx + $dy * $dy);

            if ($t > 1) {
                $x = $p2[1];
                $y = $p2[0];
            } elseif ($t > 0) {
                $x += $dx * $t;
                $y += $dy * $t;
            }
        }

        $dx = $p[1] - $x;
        $dy = $p[0] - $y;

        return $dx * $dx + $dy * $dy;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route\Signature;

final readonly class RouteWaypoints
{
    /**
     * @param list<int> $waypoints
     */
    private function __construct(
        private array $waypoints,
    ) {
    }

    /**
     * @param list<int> $waypoints
     */
    public static function fromArray(array $waypoints): self
    {
        return new self($waypoints);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return list<int>
     */
    public function toArray(): array
    {
        return $this->waypoints;
    }

    public function isEmpty(): bool
    {
        return [] === $this->waypoints;
    }

    /**
     * The largest deviation across the sampled waypoints. A median of seven discards the three
     * worst by construction, which let a multi-kilometre detour through untouched (issue #2572).
     */
    public function maxDistanceInMeterTo(self $other): float
    {
        $theirs = $other->toArray();
        if (count($theirs) !== count($this->waypoints)) {
            return INF;
        }

        $distances = [];
        $counter = count($this->waypoints);
        for ($i = 0; $i < $counter; $i += 2) {
            $distances[] = $this->distanceInMeter($this->waypoints[$i], $this->waypoints[$i + 1], $theirs[$i], $theirs[$i + 1]);
        }

        if ([] === $distances) {
            return INF;
        }

        return max($distances);
    }

    private function distanceInMeter(int $latitudeA, int $longitudeA, int $latitudeB, int $longitudeB): float
    {
        $latitudeDelta = ($latitudeA - $latitudeB) / 100_000;
        $longitudeDelta = ($longitudeA - $longitudeB) / 100_000 * cos(deg2rad($latitudeA / 100_000));

        return 111_320 * sqrt($latitudeDelta ** 2 + $longitudeDelta ** 2);
    }
}

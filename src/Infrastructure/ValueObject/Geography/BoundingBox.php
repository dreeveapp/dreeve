<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

final readonly class BoundingBox implements \JsonSerializable
{
    private function __construct(
        private float $minLng,
        private float $minLat,
        private float $maxLng,
        private float $maxLat,
    ) {
    }

    /**
     * @param non-empty-list<array{float, float}> $lngLatPairs
     */
    public static function fromLngLatPairs(array $lngLatPairs): self
    {
        $minLng = $maxLng = $lngLatPairs[0][0];
        $minLat = $maxLat = $lngLatPairs[0][1];

        foreach ($lngLatPairs as [$longitude, $latitude]) {
            $minLng = min($minLng, $longitude);
            $maxLng = max($maxLng, $longitude);
            $minLat = min($minLat, $latitude);
            $maxLat = max($maxLat, $latitude);
        }

        return new self($minLng, $minLat, $maxLng, $maxLat);
    }

    /**
     * @param array{float, float, float, float} $bounds [minLng, minLat, maxLng, maxLat]
     */
    public static function fromArray(array $bounds): self
    {
        return new self($bounds[0], $bounds[1], $bounds[2], $bounds[3]);
    }

    public function widenedWhenSpanningTheAntimeridian(): self
    {
        if ($this->maxLng - $this->minLng <= 180) {
            return $this;
        }

        return new self(-180.0, $this->minLat, 180.0, $this->maxLat);
    }

    public function overlaps(self $other): bool
    {
        if ($other->maxLng < $this->minLng) {
            return false;
        }
        if ($other->minLng > $this->maxLng) {
            return false;
        }
        if ($other->maxLat < $this->minLat) {
            return false;
        }

        return $other->minLat <= $this->maxLat;
    }

    public function diagonalInDegrees(): float
    {
        return hypot($this->maxLng - $this->minLng, $this->maxLat - $this->minLat);
    }

    public function getMinLng(): float
    {
        return $this->minLng;
    }

    public function getMinLat(): float
    {
        return $this->minLat;
    }

    public function getMaxLng(): float
    {
        return $this->maxLng;
    }

    public function getMaxLat(): float
    {
        return $this->maxLat;
    }

    /**
     * @return array{float, float, float, float}
     */
    public function jsonSerialize(): array
    {
        return [$this->minLng, $this->minLat, $this->maxLng, $this->maxLat];
    }
}

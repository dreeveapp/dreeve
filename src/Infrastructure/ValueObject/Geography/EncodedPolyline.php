<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\Geography;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class EncodedPolyline extends NonEmptyStringLiteral
{
    private const int PRECISION = 5;

    /**
     * @param array<int, array{float, float}> $coordinates
     */
    public static function fromCoordinates(array $coordinates): self
    {
        $encoded = '';
        $previousLat = $previousLng = 0;

        foreach ($coordinates as [$latitude, $longitude]) {
            $lat = (int) round($latitude * 10 ** self::PRECISION);
            $lng = (int) round($longitude * 10 ** self::PRECISION);

            $encoded .= self::encodeValue($lat - $previousLat);
            $encoded .= self::encodeValue($lng - $previousLng);

            $previousLat = $lat;
            $previousLng = $lng;
        }

        return self::fromString($encoded);
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);

        $chunk = '';
        while ($value >= 0x20) {
            $chunk .= chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        return $chunk.chr(($value & 0x1F) + 63);
    }

    public function getStartingCoordinate(): Coordinate
    {
        $points = $this->decodePoints(maxPoints: 2);

        return Coordinate::createFromLatAndLng(
            latitude: Latitude::fromString((string) $points[0]),
            longitude: Longitude::fromString((string) $points[1]),
        );
    }

    /**
     * @return float[]
     */
    public function decode(): array
    {
        return $this->decodePoints();
    }

    /**
     * @return array<int, array<float, float>>
     */
    public function decodeAndPairLngLat(): array
    {
        return array_map(
            fn (array $pair): array => [$pair[1], $pair[0]],
            array_chunk($this->decode(), 2)
        );
    }

    /**
     * @return array<int, array<float, float>>
     */
    public function decodeAndPairLatLng(): array
    {
        return array_map(
            fn (array $pair): array => [$pair[0], $pair[1]],
            array_chunk($this->decode(), 2)
        );
    }

    /**
     * @return float[]
     */
    private function decodePoints(?int $maxPoints = null): array
    {
        $encodedPolyline = (string) $this;
        $points = [];
        $index = $i = 0;
        $previous = [0, 0];

        while ($i < strlen($encodedPolyline) && (null === $maxPoints || count($points) < $maxPoints)) {
            $shift = $result = 0x00;
            do {
                $bit = ord($encodedPolyline[$i++]) - 63;
                $result |= ($bit & 0x1F) << $shift;
                $shift += 5;
            } while ($bit >= 0x20);

            $diff = (($result & 1) !== 0) ? ~($result >> 1) : ($result >> 1);
            $number = $previous[$index % 2] + $diff;
            $previous[$index % 2] = $number;
            $points[] = $number / 10 ** self::PRECISION;
            ++$index;
        }

        return $points;
    }
}

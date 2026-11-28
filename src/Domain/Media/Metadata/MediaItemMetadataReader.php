<?php

declare(strict_types=1);

namespace App\Domain\Media\Metadata;

use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class MediaItemMetadataReader
{
    public function read(string $content): MediaItemMetadata
    {
        $imageSize = @getimagesizefromstring($content);

        $exif = false;
        if (false !== ($stream = fopen('php://memory', 'r+b'))) {
            fwrite($stream, $content);
            rewind($stream);
            $exif = @exif_read_data($stream);
            fclose($stream);
        }
        $exif = is_array($exif) ? $exif : [];

        $coordinate = null;
        $latitude = $this->dmsToDecimal(
            dms: $exif['GPSLatitude'] ?? null,
            ref: $exif['GPSLatitudeRef'] ?? null,
            negativeRef: 'S',
        );
        $longitude = $this->dmsToDecimal(
            dms: $exif['GPSLongitude'] ?? null,
            ref: $exif['GPSLongitudeRef'] ?? null,
            negativeRef: 'W',
        );
        if (null !== $latitude && null !== $longitude && (0.0 !== $latitude || 0.0 !== $longitude)) {
            try {
                $coordinate = Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString((string) $latitude),
                    longitude: Longitude::fromString((string) $longitude),
                );
            } catch (\InvalidArgumentException) {
            }
        }

        $takenOn = null;
        if (is_string($exif['DateTimeOriginal'] ?? null)) {
            try {
                $takenOn = SerializableDateTime::createFromFormat(
                    format: 'Y:m:d H:i:s',
                    datetime: trim($exif['DateTimeOriginal']),
                );
            } catch (\InvalidArgumentException) {
            }
        }

        $width = false !== $imageSize && $imageSize[0] > 0 ? $imageSize[0] : null;
        $height = false !== $imageSize && $imageSize[1] > 0 ? $imageSize[1] : null;
        $orientation = is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        if (in_array($orientation, [5, 6, 7, 8], true)) {
            [$width, $height] = [$height, $width];
        }

        return new MediaItemMetadata(
            width: $width,
            height: $height,
            coordinate: $coordinate,
            takenOn: $takenOn,
            orientation: $orientation,
        );
    }

    private function dmsToDecimal(mixed $dms, mixed $ref, string $negativeRef): ?float
    {
        if (!is_array($dms) || 3 !== count($dms)) {
            return null;
        }

        $decimal = 0.0;
        foreach (array_values($dms) as $index => $rational) {
            if (!is_string($rational) || !preg_match('/^(\d+)\/(\d+)$/', $rational, $matches) || 0 === (int) $matches[2]) {
                return null;
            }
            $decimal += ((int) $matches[1] / (int) $matches[2]) / (60 ** $index);
        }

        return $negativeRef === $ref ? -$decimal : $decimal;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media\Metadata;

use App\Domain\Media\Metadata\ExifOrientation;
use App\Domain\Media\Metadata\MediaItemMetadataReader;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class MediaItemMetadataReaderTest extends TestCase
{
    public function testReadImageWithExif(): void
    {
        $metadata = new MediaItemMetadataReader()->read((string) file_get_contents(__DIR__.'/fixtures/image-with-exif.jpg'));

        $this->assertSame(3, $metadata->getWidth());
        $this->assertSame(4, $metadata->getHeight());
        $this->assertSame(ExifOrientation::ROTATE_90_CW, $metadata->getExifOrientation());
        $this->assertEquals(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('-22.9519'),
                longitude: Longitude::fromString('-43.2105'),
            ),
            $metadata->getCoordinate(),
        );
        $this->assertEquals(
            SerializableDateTime::fromString('2025-06-14 08:42:17'),
            $metadata->getTakenOn(),
        );
    }

    public function testReadImageWithoutExif(): void
    {
        $metadata = new MediaItemMetadataReader()->read((string) file_get_contents(__DIR__.'/fixtures/image-without-exif.jpg'));

        $this->assertSame(4, $metadata->getWidth());
        $this->assertSame(3, $metadata->getHeight());
        $this->assertSame(ExifOrientation::NORMAL, $metadata->getExifOrientation());
        $this->assertNull($metadata->getCoordinate());
        $this->assertNull($metadata->getTakenOn());
    }

    public function testReadNonImageContent(): void
    {
        $metadata = new MediaItemMetadataReader()->read("#EXTM3U\n#EXT-X-VERSION:3\n");

        $this->assertNull($metadata->getWidth());
        $this->assertNull($metadata->getHeight());
        $this->assertNull($metadata->getCoordinate());
        $this->assertNull($metadata->getTakenOn());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Media;

use App\Domain\Media\MediaItem;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class MediaItemBuilder
{
    private MediaItemId $mediaItemId;
    private MediaItemPath $path;
    private ?int $width = null;
    private ?int $height = null;
    private ?Coordinate $coordinate = null;
    private ?SerializableDateTime $takenOn = null;

    private function __construct()
    {
        $this->mediaItemId = MediaItemId::fromUnprefixed('1');
        $this->path = MediaItemPath::fromString('activities/1.jpg');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): MediaItem
    {
        return MediaItem::create(
            mediaItemId: $this->mediaItemId,
            path: $this->path,
            width: $this->width,
            height: $this->height,
            coordinate: $this->coordinate,
            takenOn: $this->takenOn,
        );
    }

    public function withMediaItemId(MediaItemId $mediaItemId): self
    {
        $this->mediaItemId = $mediaItemId;

        return $this;
    }

    public function withPath(MediaItemPath $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function withWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function withHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function withCoordinate(?Coordinate $coordinate): self
    {
        $this->coordinate = $coordinate;

        return $this;
    }

    public function withTakenOn(?SerializableDateTime $takenOn): self
    {
        $this->takenOn = $takenOn;

        return $this;
    }
}

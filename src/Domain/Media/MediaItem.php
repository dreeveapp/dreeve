<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'MediaItem')]
final readonly class MediaItem
{
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private MediaItemId $mediaItemId,
        #[ORM\Column(type: 'string')]
        private MediaItemPath $path,
        #[ORM\Column(type: 'string', enumType: MediaItemType::class)]
        private MediaItemType $type,
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $width,
        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $height,
        #[ORM\Embedded(class: Coordinate::class)]
        private ?Coordinate $coordinate,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?SerializableDateTime $takenOn,
    ) {
    }

    public static function create(
        MediaItemId $mediaItemId,
        MediaItemPath $path,
        ?int $width,
        ?int $height,
        ?Coordinate $coordinate,
        ?SerializableDateTime $takenOn,
    ): self {
        return new self(
            mediaItemId: $mediaItemId,
            path: $path,
            type: MediaItemType::IMAGE,
            width: $width,
            height: $height,
            coordinate: $coordinate,
            takenOn: $takenOn,
        );
    }

    public static function fromState(
        MediaItemId $mediaItemId,
        MediaItemPath $path,
        MediaItemType $type,
        ?int $width,
        ?int $height,
        ?Coordinate $coordinate,
        ?SerializableDateTime $takenOn,
    ): self {
        return new self(
            mediaItemId: $mediaItemId,
            path: $path,
            type: $type,
            width: $width,
            height: $height,
            coordinate: $coordinate,
            takenOn: $takenOn,
        );
    }

    public function getId(): MediaItemId
    {
        return $this->mediaItemId;
    }

    public function getPath(): MediaItemPath
    {
        return $this->path;
    }

    public function getType(): MediaItemType
    {
        return $this->type;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getCoordinate(): ?Coordinate
    {
        return $this->coordinate;
    }

    public function getTakenOn(): ?SerializableDateTime
    {
        return $this->takenOn;
    }

    public function withCoordinate(?Coordinate $coordinate): self
    {
        return clone ($this, [
            'coordinate' => $coordinate,
        ]);
    }

    public function withTakenOn(?SerializableDateTime $takenOn): self
    {
        return clone ($this, [
            'takenOn' => $takenOn,
        ]);
    }

    public function getOrientation(): ImageOrientation
    {
        if (null === $this->width || null === $this->height) {
            return ImageOrientation::LANDSCAPE;
        }

        return ImageOrientation::fromWidthAndHeight($this->width, $this->height);
    }
}

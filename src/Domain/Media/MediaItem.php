<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'MediaItem')]
final class MediaItem
{
    use RecordsEvents;

    public const int MAX_DISTANCE_FROM_ROUTE_IN_METER = 300;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private readonly MediaItemId $mediaItemId,
        #[ORM\Column(type: 'string')]
        private readonly MediaItemPath $path,
        #[ORM\Column(type: 'string', enumType: MediaItemType::class)]
        private readonly MediaItemType $type,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $width,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $height,
        #[ORM\Embedded(class: Coordinate::class)]
        private readonly ?Coordinate $coordinate,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private readonly ?SerializableDateTime $takenOn,
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
        $mediaItem = clone ($this, [
            'coordinate' => $coordinate,
        ]);
        $mediaItem->recordOnlyOnce(new MediaItemWasUpdated($this->mediaItemId));

        return $mediaItem;
    }

    public function withTakenOn(?SerializableDateTime $takenOn): self
    {
        $mediaItem = clone ($this, [
            'takenOn' => $takenOn,
        ]);
        $mediaItem->recordOnlyOnce(new MediaItemWasUpdated($this->mediaItemId));

        return $mediaItem;
    }

    public function getOrientation(): ImageOrientation
    {
        if (null === $this->width || null === $this->height) {
            return ImageOrientation::LANDSCAPE;
        }

        return ImageOrientation::fromWidthAndHeight($this->width, $this->height);
    }
}

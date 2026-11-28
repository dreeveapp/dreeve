<?php

declare(strict_types=1);

namespace App\Domain\Media\Metadata;

use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class MediaItemMetadata
{
    public function __construct(
        private ?int $width,
        private ?int $height,
        private ?Coordinate $coordinate,
        private ?SerializableDateTime $takenOn,
        private int $orientation,
    ) {
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

    public function getOrientation(): int
    {
        return $this->orientation;
    }
}

<?php

namespace App\Domain\Activity\Media;

use App\Domain\Activity\Activity;
use App\Domain\Media\ImageOrientation;
use App\Domain\Media\MediaItem;
use App\Infrastructure\ValueObject\Geography\Coordinate;

final readonly class ActivityMediaItem
{
    private function __construct(
        private MediaItem $mediaItem,
        private Activity $activity,
    ) {
    }

    public static function create(
        MediaItem $mediaItem,
        Activity $activity,
    ): self {
        return new self(
            mediaItem: $mediaItem,
            activity: $activity,
        );
    }

    public function getImageUrl(): string
    {
        return '/'.$this->mediaItem->getPath()->toLocalPath();
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getOrientation(): ImageOrientation
    {
        return $this->mediaItem->getOrientation();
    }

    public function getCoordinate(): ?Coordinate
    {
        return $this->mediaItem->getCoordinate();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilterables(): array
    {
        return [
            'sportType' => $this->activity->getSportType()->value,
            'countryCode' => $this->activity->getRouteGeography()->getPassedThroughCountries(),
        ];
    }
}

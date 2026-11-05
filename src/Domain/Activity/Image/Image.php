<?php

namespace App\Domain\Activity\Image;

use App\Domain\Activity\Activity;
use App\Domain\Image\ImageOrientation;

final readonly class Image
{
    private function __construct(
        private string $imageLocation,
        private Activity $activity,
        private ImageOrientation $orientation,
    ) {
    }

    public static function create(
        string $imageLocation,
        Activity $activity,
        ImageOrientation $orientation,
    ): self {
        return new self(
            imageLocation: $imageLocation,
            activity: $activity,
            orientation: $orientation,
        );
    }

    public function getImageUrl(): string
    {
        if (str_starts_with($this->imageLocation, '/')) {
            return $this->imageLocation;
        }

        return '/'.$this->imageLocation;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getOrientation(): ImageOrientation
    {
        return $this->orientation;
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

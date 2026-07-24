<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;

final class SegmentBuilder
{
    private SegmentId $segmentId;
    private Name $name;
    private SportType $sportType = SportType::RIDE;
    private Kilometer $distance;
    private float $maxGradient = 5.3;
    private bool $isFavourite = false;
    private ?string $deviceName = 'Polar';
    private readonly ?int $climbCategory;
    private readonly ?string $countryCode;
    private bool $detailsHaveBeenImported = false;
    private ?EncodedPolyline $polyline;
    private readonly ?Coordinate $startingCoordinate;
    private ?float $averageGradient = 2.7;

    private function __construct()
    {
        $this->segmentId = SegmentId::fromUnprefixed('1');
        $this->name = Name::fromString('Segment');
        $this->distance = Kilometer::from(1);
        $this->climbCategory = null;
        $this->countryCode = 'BE';
        $this->polyline = null;
        $this->startingCoordinate = null;
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): Segment
    {
        return Segment::fromState(
            segmentId: $this->segmentId,
            name: $this->name,
            sportType: $this->sportType,
            distance: $this->distance,
            maxGradient: $this->maxGradient,
            isFavourite: $this->isFavourite,
            climbCategory: $this->climbCategory,
            deviceName: $this->deviceName,
            countryCode: $this->countryCode,
            detailsHaveBeenImported: $this->detailsHaveBeenImported,
            polyline: $this->polyline,
            startingCoordinate: $this->startingCoordinate,
            averageGradient: $this->averageGradient,
        );
    }

    public function withSegmentId(SegmentId $id): self
    {
        $this->segmentId = $id;

        return $this;
    }

    public function withName(Name $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withSportType(SportType $sportType): self
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function withDistance(Kilometer $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function withMaxGradient(float $maxGradient): self
    {
        $this->maxGradient = $maxGradient;

        return $this;
    }

    public function withIsFavourite(bool $isFavourite): self
    {
        $this->isFavourite = $isFavourite;

        return $this;
    }

    public function withDeviceName(string $deviceName): self
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function withDetailsHaveBeenImported(bool $flag): self
    {
        $this->detailsHaveBeenImported = $flag;

        return $this;
    }

    public function withPolyline(EncodedPolyline $polyline): self
    {
        $this->polyline = $polyline;

        return $this;
    }

    public function withAverageGradient(?float $averageGradient): self
    {
        $this->averageGradient = $averageGradient;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\EnrichActivity;

use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Infrastructure\ValueObject\Geography\Coordinate;

final readonly class UnreachableNominatimStub implements Nominatim
{
    public function reverseGeocode(Coordinate $coordinate): array
    {
        throw new CouldNotReverseGeocodeAddress();
    }
}

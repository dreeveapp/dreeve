<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\Geocoding\Nominatim;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Infrastructure\ValueObject\Geography\Coordinate;

class SpyNominatim implements Nominatim
{
    private bool $triggerExceptionOnNextCall = false;

    public function reverseGeocode(Coordinate $coordinate): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new CouldNotReverseGeocodeAddress();
        }

        return [
            'country_code' => 'be',
            'state' => 'West Vlaanderen',
            RouteGeography::IS_REVERSE_GEOCODED => true,
        ];
    }

    public function triggerExceptionOnNextCall(): void
    {
        $this->triggerExceptionOnNextCall = true;
    }
}

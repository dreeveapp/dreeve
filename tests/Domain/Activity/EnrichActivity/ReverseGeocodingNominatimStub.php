<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\EnrichActivity;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Infrastructure\ValueObject\Geography\Coordinate;

final readonly class ReverseGeocodingNominatimStub implements Nominatim
{
    public function reverseGeocode(Coordinate $coordinate): array
    {
        return [
            'road' => 'Kleine Bergstraat',
            'town' => 'Tienen',
            'state' => 'Vlaams-Brabant',
            'country_code' => 'be',
            RouteGeography::IS_REVERSE_GEOCODED => true,
        ];
    }
}

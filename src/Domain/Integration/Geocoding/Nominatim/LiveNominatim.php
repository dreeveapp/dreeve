<?php

declare(strict_types=1);

namespace App\Domain\Integration\Geocoding\Nominatim;

use App\Application\AppName;
use App\Domain\Activity\Route\RouteGeography;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\NetworkException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

final readonly class LiveNominatim implements Nominatim
{
    public function __construct(
        private Client $client,
        private Clock $clock,
    ) {
    }

    public function reverseGeocode(Coordinate $coordinate): array
    {
        try {
            $response = $this->client->request(
                'GET',
                'https://nominatim.openstreetmap.org/reverse',
                [
                    RequestOptions::HEADERS => [
                        'User-Agent' => AppName::LABEL.' App',
                    ],
                    RequestOptions::QUERY => [
                        'lat' => $coordinate->getLatitude()->toFloat(),
                        'lon' => $coordinate->getLongitude()->toFloat(),
                        'format' => 'json',
                    ],
                ]
            );

            $response = Json::decode($response->getBody()->getContents());
            $this->clock->sleep(1);
        } catch (NetworkException|RequestException) {
            throw new CouldNotReverseGeocodeAddress();
        }

        if (!isset($response['address'])) {
            throw new CouldNotReverseGeocodeAddress();
        }

        return [
            ...$response['address'],
            RouteGeography::IS_REVERSE_GEOCODED => true,
        ];
    }
}

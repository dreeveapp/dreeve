<?php

namespace App\Tests\Domain\Integration\Geocoding\Nominatim;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\LiveNominatim;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\Infrastructure\Time\Sleep\NullSleep;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class LiveNominatimTest extends TestCase
{
    use MatchesSnapshots;

    private Nominatim $nominatim;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject&\GuzzleHttp\Client
     */
    private MockObject $client;

    public function testReverseGeocode(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options): Response {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://nominatim.openstreetmap.org/reverse', $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [], Json::encode([
                    'address' => ['state' => 'BruHHE'],
                ]));
            });

        $this->assertEquals(
            ['state' => 'BruHHE', RouteGeography::IS_REVERSE_GEOCODED => true],
            $this->nominatim->reverseGeocode(
                Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString('80'),
                    longitude: Longitude::fromString('100'),
                )
            )
        );
    }

    public function testReverseGeocodeWhenException(): void
    {
        $this->expectExceptionObject(new CouldNotReverseGeocodeAddress());

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ConnectException(
                message: 'test',
                request: new Request('GET', 'https://nominatim.openstreetmap.org/reverse'),
            ));

        $this->nominatim->reverseGeocode(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('80'),
                longitude: Longitude::fromString('100'),
            )
        );
    }

    public function testReverseGeocodeWhenError(): void
    {
        $this->expectExceptionObject(new CouldNotReverseGeocodeAddress());

        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options): Response {
                $this->assertEquals('GET', $method);
                $this->assertEquals('https://nominatim.openstreetmap.org/reverse', $path);

                return new Response(200, [], Json::encode([
                    'error' => ['lollolo'],
                ]));
            });

        $this->nominatim->reverseGeocode(
            Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('80'),
                longitude: Longitude::fromString('100'),
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);

        $this->nominatim = new LiveNominatim(
            $this->client,
            new NullSleep(),
        );
    }
}

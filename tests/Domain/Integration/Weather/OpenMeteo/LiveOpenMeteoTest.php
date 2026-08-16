<?php

namespace App\Tests\Domain\Integration\Weather\OpenMeteo;

use App\Domain\Integration\Weather\OpenMeteo\LiveOpenMeteo;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoArchiveApiCallHasFailed;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoForecastApiCallHasFailed;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class LiveOpenMeteoTest extends TestCase
{
    use MatchesSnapshots;

    private LiveOpenMeteo $liveOpenMeteo;
    /**
     * @var MockObject&Client
     */
    private MockObject $client;
    private Clock $clock;

    public function testGetWeatherStats(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options): Response {
                $this->assertEquals('GET', $method);
                $this->assertEquals('v1/forecast', $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [], Json::encode([]));
            });

        $this->liveOpenMeteo->getWeatherStats(
            coordinate: Coordinate::createFromLatAndLng(
                Latitude::fromString('80'),
                Longitude::fromString('100')
            ),
            date: SerializableDateTime::fromString('2023-10-31'),
        );
    }

    public function testGetWeatherStatsInArchive(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $options): Response {
                $this->assertEquals('GET', $method);
                $this->assertEquals('v1/archive', $path);
                $this->assertMatchesJsonSnapshot($options);

                return new Response(200, [], Json::encode([]));
            });

        $this->liveOpenMeteo->getWeatherStats(
            coordinate: Coordinate::createFromLatAndLng(
                Latitude::fromString('80'),
                Longitude::fromString('100')
            ),
            date: SerializableDateTime::fromString('2023-09-31'),
        );
    }

    public function testGetWeatherStatsWhenForecastApiIsUnavailable(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ServerException(
                'Server error: 503 Service Unavailable',
                new Request('GET', 'v1/forecast'),
                new Response(503, [], Json::encode(['reason' => 'The service is overloaded', 'error' => true]))
            ));

        $this->expectExceptionObject(new OpenMeteoForecastApiCallHasFailed());

        $this->liveOpenMeteo->getWeatherStats(
            coordinate: Coordinate::createFromLatAndLng(
                Latitude::fromString('80'),
                Longitude::fromString('100')
            ),
            date: SerializableDateTime::fromString('2023-10-31'),
        );
    }

    public function testGetWeatherStatsWhenArchiveApiIsUnavailable(): void
    {
        $this->client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ServerException(
                'Server error: 503 Service Unavailable',
                new Request('GET', 'v1/archive'),
                new Response(503, [], Json::encode(['reason' => 'The service is overloaded', 'error' => true]))
            ));

        $this->expectExceptionObject(new OpenMeteoArchiveApiCallHasFailed());

        $this->liveOpenMeteo->getWeatherStats(
            coordinate: Coordinate::createFromLatAndLng(
                Latitude::fromString('80'),
                Longitude::fromString('100')
            ),
            date: SerializableDateTime::fromString('2023-09-31'),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);
        $this->clock = PausedClock::on(SerializableDateTime::fromString('2023-10-31'));

        $this->liveOpenMeteo = new LiveOpenMeteo(
            $this->client,
            $this->clock
        );
    }
}

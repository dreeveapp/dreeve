<?php

namespace App\Tests\Controller\Api\V1\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class SegmentSearchRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private const string PATH = '/api/v1/segments';

    private ApiToken $token;

    public function testItListsSegmentsWithTheirBestEffort(): void
    {
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        $segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Alpe du Zwift'))
            ->withPolyline(EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC_mqNvxq`@'))
            ->build());
        $segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromString('segment-custom-1'))
            ->withName(Name::fromString('Brugse Poort'))
            ->withType(SegmentType::CUSTOM)
            ->withAverageGradient(null)
            ->build());
        $segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
        foreach ([['1', 300.0, '2023-10-10 10:00:00'], ['2', 250.0, '2023-10-11 08:30:00'], ['3', 250.0, '2023-10-12 09:00:00']] as [$id, $elapsedTime, $startDateTime]) {
            $segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($id))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->withActivityId(ActivityId::fromUnprefixed($id))
                ->withElapsedTimeInSeconds($elapsedTime)
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->build());
        }

        $this->client->request('GET', self::PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItFiltersOnSportTypesAndPaginates(): void
    {
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        foreach ([['1', 'A', SportType::RIDE], ['2', 'B', SportType::RUN], ['3', 'C', SportType::VIRTUAL_RIDE], ['4', 'D', SportType::RIDE]] as [$id, $name, $sportType]) {
            $segmentRepository->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withName(Name::fromString($name))
                ->withSportType($sportType)
                ->build());
        }

        $this->client->request('GET', self::PATH.'?filters[sportType]=Ride,VirtualRide&pagination[page]=2&pagination[size]=2', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = Json::decode((string) $this->client->getResponse()->getContent());
        $this->assertSame(['segment-4'], array_column($response['segments'], 'id'));
        $this->assertSame(['page' => 2, 'size' => 2, 'total' => 3, 'totalPages' => 2], $response['pagination']);
    }

    public function testItFiltersOnBounds(): void
    {
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        foreach ([['1', 51.05, 3.72], ['2', 50.85, 4.35], ['3', 51.20, 3.10]] as [$id, $latitude, $longitude]) {
            $segmentRepository->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString((string) $latitude),
                    longitude: Longitude::fromString((string) $longitude),
                ))
                ->build());
        }
        $segmentRepository->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('4'))
            ->build());

        $this->client->request('GET', self::PATH.'?filters[bounds]=51.0,3.5,51.1,4.0', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = Json::decode((string) $this->client->getResponse()->getContent());
        $this->assertSame(['segment-1'], array_column($response['segments'], 'id'));
        $this->assertSame(1, $response['pagination']['total']);
    }

    public function testItFiltersOnBoundsSpanningTheAntimeridian(): void
    {
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        foreach ([['1', -17.7, 179.5], ['2', -17.8, -179.5], ['3', -17.7, 170.0]] as [$id, $latitude, $longitude]) {
            $segmentRepository->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    latitude: Latitude::fromString((string) $latitude),
                    longitude: Longitude::fromString((string) $longitude),
                ))
                ->build());
        }

        $this->client->request('GET', self::PATH.'?filters[bounds]=-18,179,-17,-179', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = Json::decode((string) $this->client->getResponse()->getContent());
        $this->assertSame(['segment-1', 'segment-2'], array_column($response['segments'], 'id'));
        $this->assertSame(2, $response['pagination']['total']);
    }

    #[DataProvider('provideInvalidQueryParameters')]
    public function testItRejectsInvalidQueryParameters(string $queryString): void
    {
        $this->client->request('GET', self::PATH.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            'bad_request',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidQueryParameters(): iterable
    {
        yield 'an unknown sport type' => ['?filters[sportType]=Quidditch'];
        yield 'bounds with three values' => ['?filters[bounds]=51.0,3.5,51.1'];
        yield 'bounds with a non-numeric value' => ['?filters[bounds]=51.0,3.5,north,4.0'];
        yield 'bounds with a latitude out of range' => ['?filters[bounds]=51.0,3.5,91,4.0'];
        yield 'bounds with a longitude out of range' => ['?filters[bounds]=51.0,-181,51.1,4.0'];
        yield 'bounds with south above north' => ['?filters[bounds]=51.1,3.5,51.0,4.0'];
        yield 'a page size above the maximum' => ['?pagination[size]=101'];
        yield 'a page number of zero' => ['?pagination[page]=0'];
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = ApiToken::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}

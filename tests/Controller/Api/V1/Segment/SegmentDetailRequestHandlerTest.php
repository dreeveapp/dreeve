<?php

namespace App\Tests\Controller\Api\V1\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
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
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class SegmentDetailRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private const string PATH = '/api/v1/segments';

    private ApiToken $token;

    public function testItReturnsASingleSegment(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Alpe du Zwift'))
            ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.05'),
                longitude: Longitude::fromString('3.72'),
            ))
            ->withPolyline(EncodedPolyline::fromString('_p~iF~ps|U_ulLnnqC_mqNvxq`@'))
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

        $this->client->request('GET', self::PATH.'/segment-1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[TestWith(['segment-1'])]
    #[TestWith(['1'])]
    public function testItReportsAnUnknownOrMalformedSegmentAsNotFound(string $segmentId): void
    {
        $this->client->request('GET', self::PATH.'/'.$segmentId, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
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

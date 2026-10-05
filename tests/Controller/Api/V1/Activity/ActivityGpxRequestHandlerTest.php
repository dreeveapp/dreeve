<?php

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

class ActivityGpxRequestHandlerTest extends ControllerWebTestCase
{
    private Token $token;

    public function testItExportsTheActivityAsAGpxAttachment(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName('Morning Ride')
                ->withSportType(SportType::RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2026-08-19 08:30:00'))
                ->build(),
            rawData: [],
        ));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        foreach ([
            StreamType::LAT_LNG->value => [[51.2, 3.2], null, [51.21, 3.21]],
            StreamType::TIME->value => [0, 1, 2],
            StreamType::ALTITUDE->value => [10.5, 11.0, 11.5],
            StreamType::WATTS->value => [200, 210, 220],
            StreamType::HEART_RATE->value => [120, 121, 122],
            StreamType::CADENCE->value => [80, 81, 82],
            StreamType::TEMP->value => [20, 20, 21],
        ] as $streamType => $data) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-1/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/gpx+xml; charset=UTF-8');
        $this->assertResponseHeaderSame(
            'Content-Disposition',
            'attachment; filename=2026-08-19-morning-ride.gpx'
        );

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<trk>', $content);
        $this->assertSame(2, substr_count($content, '<trkpt lat='));
        $this->assertSame(2, substr_count($content, '<trkpt'));
    }

    public function testItReportsWhenAnActivityHasNoGpxData(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::ROWING)
                ->build(),
            rawData: [],
        ));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        foreach ([StreamType::TIME, StreamType::HEART_RATE, StreamType::CADENCE] as $streamType) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData([0, 1, 2])
                ->build());
        }

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-1/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame([
            'error' => 'gpx_not_available',
            'message' => 'Activity "activity-1" has no GPS data to export as GPX.',
        ], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    #[TestWith(['activity-1'])]
    #[TestWith(['not-an-activity-id'])]
    public function testItReportsAnUnknownOrMalformedActivityAsNotFound(string $activityId): void
    {
        $this->client->request(
            'GET',
            '/api/v1/activities/'.$activityId.'/gpx',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}

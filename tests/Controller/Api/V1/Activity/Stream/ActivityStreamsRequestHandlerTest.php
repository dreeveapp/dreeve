<?php

namespace App\Tests\Controller\Api\V1\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

class ActivityStreamsRequestHandlerTest extends ControllerWebTestCase
{
    private Token $token;

    public function testItReturnsTheStreamsOfAnActivityWithoutGps(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->build(),
            rawData: [],
        ));
        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        foreach ([
            StreamType::TIME->value => [0, 1, 2],
            StreamType::HEART_RATE->value => [120, 121, 122],
            StreamType::CADENCE->value => [80, 81, 82],
            StreamType::WATTS->value => [200, 210, 220],
        ] as $streamType => $data) {
            $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType(StreamType::from($streamType))
                ->withData($data)
                ->build());
        }

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-1/streams',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame([
            'timeInSeconds' => [0, 1, 2],
            'distanceInMeter' => null,
            'latLng' => null,
            'altitudeInMeter' => null,
            'velocityInMeterPerSecond' => null,
            'heartRate' => [120, 121, 122],
            'cadence' => [80, 81, 82],
            'powerInWatts' => [200, 210, 220],
            'temperatureInCelsius' => null,
            'moving' => null,
            'gradeInPercent' => null,
        ], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    #[TestWith(['activity-1'])]
    #[TestWith(['not-an-activity-id'])]
    public function testItReportsAnUnknownOrMalformedActivityAsNotFound(string $activityId): void
    {
        $this->client->request(
            'GET',
            '/api/v1/activities/'.$activityId.'/streams',
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

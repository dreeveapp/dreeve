<?php

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class ActivitySegmentEffortsRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private ApiToken $token;

    public function testItReturnsTheSegmentEffortsOfAnActivity(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            rawData: [],
        ));
        $segmentRepository = $this->getContainer()->get(SegmentRepository::class);
        foreach (['1' => 'Alpe du Zwift', '2' => 'Epic KOM'] as $segmentId => $name) {
            $segmentRepository->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed((string) $segmentId))
                ->withName(Name::fromString($name))
                ->build());
        }
        $segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
        foreach ([
            ['1', '1', '1', 300.0, '2023-10-10 10:00:00'],
            ['2', '2', '1', 600.0, '2023-10-10 10:30:00'],
            ['3', '1', '2', 250.0, '2023-10-11 08:30:00'],
        ] as [$segmentEffortId, $segmentId, $activityId, $elapsedTime, $startDateTime]) {
            $segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($segmentEffortId))
                ->withSegmentId(SegmentId::fromUnprefixed($segmentId))
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withElapsedTimeInSeconds($elapsedTime)
                ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
                ->withDistance(Kilometer::from(1.5))
                ->withAverageWatts(250.5)
                ->withAverageHeartRate(150)
                ->build());
        }

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-1/segments',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());

        $this->client->request(
            'GET',
            '/api/v1/activities/activity-903645/segments',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [
                'segmentEfforts' => [],
                'pagination' => ['page' => 1, 'size' => 1, 'total' => 0, 'totalPages' => 0],
            ],
            Json::decode((string) $this->client->getResponse()->getContent())
        );
    }

    #[TestWith(['activity-1'])]
    #[TestWith(['not-an-activity-id'])]
    public function testItReportsAnUnknownOrMalformedActivityAsNotFound(string $activityId): void
    {
        $this->client->request(
            'GET',
            '/api/v1/activities/'.$activityId.'/segments',
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
        $this->token = ApiToken::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}

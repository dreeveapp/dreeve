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
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class ActivityDetailRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private const string PATH = '/api/v1/activities';

    private Token $token;

    public function testItReturnsASingleActivity(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', self::PATH.'/activity-9756441741', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItReportsAnActivityWithoutGpsAsHavingNoGpx(): void
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

        $this->client->request('GET', self::PATH.'/activity-1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertFalse(Json::decode((string) $this->client->getResponse()->getContent())['hasGpx']);
    }

    public function testItReportsAnUnknownActivityAsNotFound(): void
    {
        $this->client->request('GET', self::PATH.'/activity-1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public function testItReportsAMalformedActivityIdAsNotFound(): void
    {
        $this->client->request('GET', self::PATH.'/not-an-activity-id', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

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
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}

<?php

namespace App\Tests\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamRepository;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamTypes;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\CombinedStream\CombinedActivityStreamBuilder;
use App\Tests\ProvideTestData;

class ActivityCoordinatesRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    public function testRender(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            []
        ));
        $this->getContainer()->get(CombinedActivityStreamRepository::class)->add(
            CombinedActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamTypes(CombinedStreamTypes::fromArray([
                    CombinedStreamType::DISTANCE,
                    CombinedStreamType::ALTITUDE,
                    CombinedStreamType::LAT_LNG,
                ]))
                ->withData([
                    [0, 10, [51.2, 3.2]],
                    [10, 11, null],
                    [20, 12, [51.3, 3.3]],
                ])
                ->build()
        );

        $this->client->request('GET', '/api/internal/activities/activity-1/coordinates');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertSame('[[51.2,3.2],[51.3,3.3]]', $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/activities/activity-9756441741/coordinates');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activities.9756441741.coordinates',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, activities.9756441741',
        );
    }

    public function testItDoesNotResolveAnActivityWithoutACombinedStream(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/activities/activity-9830227112/coordinates');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/activities/activity-1/coordinates');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItRejectsAnUnprefixedActivityId(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/activities/9756441741/coordinates');

        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}

<?php

namespace App\Tests\Controller\Admin\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class ActivityRouteRequestHandlerTest extends AdminWebTestCase
{
    public function testItReturnsTheRoute(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->withActivityId(ActivityId::fromUnprefixed('1'))->build(),
            []
        ));
        foreach ([
            [StreamType::LAT_LNG, [[51.0, 4.0], [51.001, 4.0], [51.002, 4.0]]],
            [StreamType::DISTANCE, [0, 111.2, 222.4, 333.6]],
            [StreamType::ALTITUDE, [10, 11, 12]],
        ] as [$streamType, $data]) {
            $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData($data)
                ->build());
        }
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/activity-1/route');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(
            [
                'points' => [[51.0, 4.0], [51.001, 4.0], [51.002, 4.0]],
                'distance' => [0, 111.2, 222.4],
                'altitude' => [10, 11, 12],
            ],
            Json::decode($this->client->getResponse()->getContent()),
        );
    }

    public function testItReturnsNoAltitudeWhenTheStreamIsMissing(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->withActivityId(ActivityId::fromUnprefixed('1'))->build(),
            []
        ));
        foreach ([[StreamType::LAT_LNG, [[51.0, 4.0], [51.001, 4.0]]], [StreamType::DISTANCE, [0, 111.2]]] as [$streamType, $data]) {
            $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStreamType($streamType)
                ->withData($data)
                ->build());
        }
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/activity-1/route');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], Json::decode($this->client->getResponse()->getContent())['altitude']);
    }

    public function testItReturnsNotFoundWhenTheActivityHasNoRoute(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->withActivityId(ActivityId::fromUnprefixed('1'))->build(),
            []
        ));
        $this->getContainer()->get(ActivityStreamRepository::class)->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withStreamType(StreamType::DISTANCE)
            ->withData([0, 100])
            ->build());
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/activity-1/route');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItReturnsNotFoundWhenTheActivityDoesNotExist(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/activity-1/route');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNotAvailableInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/activities/activity-1/route');

        $this->assertResponseStatusCodeSame(404);
    }
}

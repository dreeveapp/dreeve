<?php

namespace App\Tests\Controller\Admin\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Media\MediaItemId;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;

class LocateActivityMediaItemByTimeRequestHandlerTest extends AdminWebTestCase
{
    public function testLocatesTheMediaItemOnTheRouteAtItsTakenOnTime(): void
    {
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride'))
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 10:00:00'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('photo'))
                ->build(),
            [],
        ));
        $activityStreamRepository = static::getContainer()->get(ActivityStreamRepository::class);
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('ride'))
            ->withStreamType(StreamType::TIME)
            ->withData([0, 10, 20])
            ->build());
        $activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('ride'))
            ->withStreamType(StreamType::LAT_LNG)
            ->withData([[50.0, 4.0], [51.0, 5.0], [52.0, 6.0]])
            ->build());

        $this->client->loginUser($this->adminUser());
        $url = '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('photo').'/locate-by-time';

        $this->client->request('POST', $url, ['takenOn' => '2026-06-01T10:00:15']);
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['latitude' => 51.5, 'longitude' => 5.5],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );

        $this->client->request('POST', $url, ['takenOn' => '']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['error' => 'Fill in “Taken on” first.'],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );

        $this->client->request('POST', $url, ['takenOn' => '2026-06-01T10:00:21']);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(
            ['error' => 'The “Taken on” time falls outside this activity.'],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );

        $this->client->request('POST', '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('other').'/locate-by-time', ['takenOn' => '2026-06-01T10:00:15']);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/activities/'.ActivityId::fromUnprefixed('unknown').'/media/'.MediaItemId::fromUnprefixed('photo').'/locate-by-time', ['takenOn' => '2026-06-01T10:00:15']);
        $this->assertResponseStatusCodeSame(404);
    }
}

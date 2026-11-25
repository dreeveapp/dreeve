<?php

declare(strict_types=1);

namespace App\Tests\Controller\Page\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use App\Tests\MatchesVersionlessSnapshots;
use App\Tests\ProvideTestData;

class ActivityCollectionRequestHandlerTest extends AdminWebTestCase
{
    use MatchesVersionlessSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('b3c1e0f2-53a7-11ee-a5e1-9a34a3268d74'))
            ->withPath(MediaItemPath::fromString('activities/b3c1e0f2-53a7-11ee-a5e1-9a34a3268d74.jpg'))
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.20081'),
                longitude: Longitude::fromString('3.18339'),
            ))
            ->build()
        );
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityWithRawData = $activityRepository->findWithRawData(ActivityId::fromUnprefixed('9830227112'));
        $activityRepository->update(ActivityWithRawData::fromState(
            activity: $activityWithRawData->getActivity()->withMediaItemIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('b3c1e0f2-53a7-11ee-a5e1-9a34a3268d74'),
            ])),
            rawData: $activityWithRawData->getRawData(),
        ));
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withName('Summer trip')
            ->withDescription('A week of cycling in the sun')
            ->withType(ActivityCollectionType::OTHER)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9756441741'),
                ActivityId::fromUnprefixed('9830227112'),
                ActivityId::fromUnprefixed('8756441741'),
                ActivityId::fromUnprefixed('deleted'),
            ]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity-collections.1.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.collections.1, activity.route, activities.9756441741, activities.9830227112, activities.8756441741, activities.deleted, media.items.b3c1e0f2-53a7-11ee-a5e1-9a34a3268d74, media.items.aea2d7b4-53a7-11ee-a5e1-9a34a3268d74',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderTrip(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withName('Week in Bruges')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9830227167'),
                ActivityId::fromUnprefixed('9830227112'),
                ActivityId::fromUnprefixed('9830232182'),
            ]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderEvent(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withName('Tour of Flanders')
            ->withType(ActivityCollectionType::EVENT)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9830227167'),
                ActivityId::fromUnprefixed('9830227112'),
                ActivityId::fromUnprefixed('9830232182'),
            ]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithDescent(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('snowboard'))
                ->withSportType(SportType::SNOWBOARD)
                ->withDescent(Meter::from(3049))
                ->build(),
            rawData: [],
        ));
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withName('Ski week')
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('snowboard')]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItRendersNoMapWithoutRealWorldRoutes(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('9756441741')]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('data-leaflet', (string) $this->client->getResponse()->getContent());
    }

    public function testItReturnsNotFound(): void
    {
        $this->client->request('GET', '/collections/activityCollection-1');
        $this->assertResponseStatusCodeSame(404);

        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1');
        $this->assertResponseStatusCodeSame(404);
    }
}

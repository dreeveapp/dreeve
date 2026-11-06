<?php

declare(strict_types=1);

namespace App\Tests\Controller\Page\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityCollectionRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
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
            'settings.appearance, activity.collections.1, activity.route, activities.9756441741, activities.9830227112, activities.8756441741, activities.deleted',
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

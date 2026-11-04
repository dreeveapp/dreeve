<?php

declare(strict_types=1);

namespace App\Tests\Controller\Page\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityCollectionsRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Summer trip')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9756441741'),
                ActivityId::fromUnprefixed('9542782314'),
                ActivityId::fromUnprefixed('deleted'),
            ]))
            ->build());
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->withName('Gran fondo')
            ->withType(ActivityCollectionType::EVENT)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('9830227112')]))
            ->withCoverImagePath('files/activity-collections/cover.jpg')
            ->build());
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('3'))
            ->withName('Not started yet')
            ->build());

        $this->client->request('GET', '/collections');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity-collections.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.collections, activities',
        );
        $this->assertStringNotContainsString('Not started yet', (string) $this->client->getResponse()->getContent());
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItReturnsNotFoundWithoutCollectionsToShow(): void
    {
        $this->client->request('GET', '/collections');
        $this->assertResponseStatusCodeSame(404);

        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
            ->build());

        $this->client->request('GET', '/collections');
        $this->assertResponseStatusCodeSame(404);
    }
}

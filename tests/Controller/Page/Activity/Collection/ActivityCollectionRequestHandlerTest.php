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
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9756441741'),
                ActivityId::fromUnprefixed('9542782314'),
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
            'settings.appearance, activity.collections.1, activities.9542782314, activities.9756441741',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
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

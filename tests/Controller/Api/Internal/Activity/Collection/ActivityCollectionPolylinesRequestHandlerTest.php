<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Internal\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityCollectionPolylinesRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9830227112'),
                ActivityId::fromUnprefixed('9756441741'),
                ActivityId::fromUnprefixed('deleted'),
            ]))
            ->build());

        $this->client->request('GET', '/api/internal/collections/activityCollection-1/polylines');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertStringEndsWith(
            'activity-collections.1.polylines',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, activity.collections.1, activity.route, activities.9756441741, activities.9830227112',
        );
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItReturnsNotFound(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/collections/activityCollection-1/polylines');
        $this->assertResponseStatusCodeSame(404);

        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
            ->build());
        $this->client->request('GET', '/api/internal/collections/activityCollection-1/polylines');
        $this->assertResponseStatusCodeSame(404);

        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('9756441741')]))
            ->build());
        $this->client->request('GET', '/api/internal/collections/activityCollection-2/polylines');
        $this->assertResponseStatusCodeSame(404);
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}

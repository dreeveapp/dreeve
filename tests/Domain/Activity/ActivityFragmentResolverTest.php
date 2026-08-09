<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityFragmentResolver;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideBuiltTestSet;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityFragmentResolverTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideBuiltTestSet;

    public function testRender(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderForAVirtualRide(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9542782314');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[DataProvider('provideSportTypesWithTheirOwnTemplate')]
    public function testItRendersTheTemplateOfTheSportType(SportType $sportType): void
    {
        $this->provideBuiltTestSet();

        $activityId = ActivityId::fromUnprefixed('123456789');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->build(),
            [],
        ));

        $this->client->request('GET', '/api/fragment/page/activity/'.$activityId);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public static function provideSportTypesWithTheirOwnTemplate(): \Generator
    {
        yield 'run' => [SportType::RUN];
        yield 'swim' => [SportType::POOL_SWIM];
    }

    public function testGetPath(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'activity.9756441741.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/data/activity/activity-9756441741');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItOnlyRendersTheEditLinkForAuthenticatedVisitors(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');
        $this->assertStringNotContainsString(
            'admin/activities/activity-9756441741/edit',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');
        $this->assertStringContainsString(
            'admin/activities/activity-9756441741/edit',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheActivityItRenders(): void
    {
        $this->provideBuiltTestSet();

        $this->client->request('GET', '/api/fragment/page/activity/activity-9756441741');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, activities.9756441741, gear',
        );
    }

    #[DataProvider('providePathsToResolve')]
    public function testResolve(string $path): void
    {
        $this->provideBuiltTestSet();

        $this->assertNull($this->getContainer()->get(ActivityFragmentResolver::class)->resolve($path));
    }

    public static function providePathsToResolve(): \Generator
    {
        yield 'an activity that does not exist' => ['activity/activity-1'];
        yield 'the unprefixed id is not a valid activity id' => ['activity/9756441741'];
        yield 'the bare base path' => ['activity'];
        yield 'a nested path' => ['activity/activity-9756441741/metrics'];
        yield 'another page entirely' => ['milestones'];
    }
}

<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityPageResolver;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ActivityPageResolverTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private ActivityPageResolver $activityPageResolver;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $page = $this->activityPageResolver->resolve('activity/activity-9756441741');
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    public function testRenderForAVirtualRide(): void
    {
        $this->provideFullTestSet();

        $page = $this->activityPageResolver->resolve('activity/activity-9542782314');
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    #[DataProvider('provideSportTypesWithTheirOwnTemplate')]
    public function testItRendersTheTemplateOfTheSportType(SportType $sportType): void
    {
        $this->provideFullTestSet();

        $activityId = ActivityId::fromUnprefixed('123456789');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withSportType($sportType)
                ->build(),
            [],
        ));

        $page = $this->activityPageResolver->resolve('activity/'.$activityId);
        $this->assertNotNull($page);
        $this->assertMatchesHtmlSnapshot($page->render());
    }

    public static function provideSportTypesWithTheirOwnTemplate(): \Generator
    {
        yield 'run' => [SportType::RUN];
        yield 'swim' => [SportType::POOL_SWIM];
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();

        $page = $this->activityPageResolver->resolve('activity/activity-9756441741');
        $this->assertNotNull($page);

        $this->assertEquals('activity/activity-9756441741', $page->getPath());
        $this->assertEquals('activity.9756441741', $page->getCacheability()->getCacheKey());
    }

    public function testItOnlyRendersTheEditLinkForAuthenticatedVisitors(): void
    {
        $this->provideFullTestSet();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');

        $page = $this->activityPageResolver->resolve('activity/activity-9756441741');
        $this->assertNotNull($page);

        $tokenStorage->setToken(null);
        $this->assertStringNotContainsString('admin/activities/activity-9756441741/edit', (string) $page->render());

        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));
        $this->assertStringContainsString('admin/activities/activity-9756441741/edit', (string) $page->render());

        $tokenStorage->setToken(null);
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();

        $page = $this->activityPageResolver->resolve('activity/activity-9756441741');
        $this->assertNotNull($page);

        $this->assertEquals(
            [AuthenticatedCacheContext::class],
            $page->getCacheability()->getCacheContexts()->toArray(),
        );
    }

    public function testItIsTaggedWithTheActivityItRenders(): void
    {
        $this->provideFullTestSet();

        $page = $this->activityPageResolver->resolve('activity/activity-9756441741');
        $this->assertNotNull($page);

        $this->assertEquals(
            ['settings.appearance', 'settings.general', 'activities.9756441741', 'gear'],
            $page->getCacheability()->getCacheTags()->toTagStrings(),
        );
    }

    #[DataProvider('providePathsToResolve')]
    public function testResolve(string $path): void
    {
        $this->provideFullTestSet();

        $this->assertNull($this->activityPageResolver->resolve($path));
    }

    public static function providePathsToResolve(): \Generator
    {
        yield 'an activity that does not exist' => ['activity/activity-1'];
        yield 'the unprefixed id is not a valid activity id' => ['activity/9756441741'];
        yield 'the bare base path' => ['activity'];
        yield 'a nested path' => ['activity/activity-9756441741/metrics'];
        yield 'another page entirely' => ['milestones'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityPageResolver = $this->getContainer()->get(ActivityPageResolver::class);
    }
}

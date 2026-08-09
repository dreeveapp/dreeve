<?php

namespace App\Tests\Infrastructure\Http\Fragment;

use App\Domain\Activity\ActivityCoordinatesFragmentResolver;
use App\Domain\Activity\ActivityFragmentResolver;
use App\Domain\Activity\ActivityMetricsFragmentResolver;
use App\Domain\Activity\ActivityPolylinesFragmentResolver;
use App\Domain\Activity\BestEffort\ActivityBestEffortsFragmentResolver;
use App\Domain\Activity\BestEffort\BestEffortsHistoryFragmentResolver;
use App\Domain\Calendar\MonthFragmentResolver;
use App\Domain\Rewind\RewindCompareFragmentResolver;
use App\Domain\Rewind\RewindFragmentResolver;
use App\Domain\Segment\ActivitySegmentsFragmentResolver;
use App\Domain\Segment\SegmentFragmentResolver;
use App\Domain\Segment\SegmentPolylinesFragmentResolver;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class FragmentCacheContextGuardTest extends ContainerTestCase
{
    use ProvideTestData;

    private const array PATH_PER_RESOLVER = [
        ActivityFragmentResolver::class => 'activity/activity-9756441741',
        MonthFragmentResolver::class => 'month/2023-06',
        RewindFragmentResolver::class => 'rewind/2023',
        RewindCompareFragmentResolver::class => 'rewind/2023/compare/2022',
        ActivityMetricsFragmentResolver::class => 'activity/activity-9756441741/metrics',
        ActivityCoordinatesFragmentResolver::class => 'activity/activity-9756441741/coordinates',
        ActivityPolylinesFragmentResolver::class => 'activity/activity-9830227112/polylines',
        ActivityBestEffortsFragmentResolver::class => 'activity/activity-9542782314/best-efforts',
        ActivitySegmentsFragmentResolver::class => 'activity/activity-9542782314/segments',
        SegmentPolylinesFragmentResolver::class => 'segment/segment-10/polylines',
        SegmentFragmentResolver::class => 'segment/segment-10',
        BestEffortsHistoryFragmentResolver::class => 'best-efforts/Ride/10000',
    ];

    public function testEveryFragmentHasAPathAndACacheKeyOfItsOwn(): void
    {
        $this->provideFragmentTestSet();

        $paths = [];
        $cacheKeys = [];
        foreach ($this->allFragments() as $fragment) {
            $paths[] = $fragment->getPath();

            if (!$fragment->getCacheability()->isCacheable()) {
                continue;
            }
            $cacheKeys[] = $fragment->getCacheability()->getCacheKey();
        }

        $this->assertNotEmpty($paths);
        $this->assertEquals(array_unique($paths), $paths);
        $this->assertEquals(array_unique($cacheKeys), $cacheKeys);
    }

    public function testEveryCacheContextHasAKeyOfItsOwn(): void
    {
        /** @var CacheContextRegistry $cacheContextRegistry */
        $cacheContextRegistry = $this->getContainer()->get(CacheContextRegistry::class);

        $keys = [];
        foreach ($cacheContextRegistry->all() as $cacheContext) {
            $keys[] = $cacheContext::getKey();
        }

        $this->assertNotEmpty($keys);
        $this->assertEquals(array_unique($keys), $keys);
    }

    public function testEveryContextDeclaredByAFragmentResolvesThroughTheRealRegistry(): void
    {
        $this->provideFragmentTestSet();

        /** @var CacheContextRegistry $cacheContextRegistry */
        $cacheContextRegistry = $this->getContainer()->get(CacheContextRegistry::class);

        foreach ($this->allFragments() as $fragment) {
            $this->assertIsString(
                $cacheContextRegistry->buildCacheKeySegments($fragment->getCacheability()->getCacheContexts())
            );
        }
    }

    private function provideFragmentTestSet(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();
    }

    /**
     * @return Fragment[]
     */
    private function allFragments(): array
    {
        /** @var FragmentRegistry $fragmentRegistry */
        $fragmentRegistry = $this->getContainer()->get(FragmentRegistry::class);

        $fragments = [];
        foreach ($fragmentRegistry->all() as $fragment) {
            if (!$fragment instanceof FragmentResolver) {
                $fragments[] = $fragment;
                continue;
            }

            $path = self::PATH_PER_RESOLVER[$fragment::class] ?? null;
            $this->assertNotNull($path, sprintf('Add a path for "%s" to %s::PATH_PER_RESOLVER.', $fragment::class, self::class));

            $resolvedFragment = $fragment->resolve($path);
            $this->assertNotNull($resolvedFragment, sprintf('"%s" does not resolve "%s" anymore, pick another path.', $fragment::class, $path));

            $fragments[] = $resolvedFragment;
        }

        return $fragments;
    }

    public function testTheAuthenticationContextResolvesToADifferentSegmentPerVisitor(): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');
        /** @var AuthenticatedCacheContext $cacheContext */
        $cacheContext = $this->getContainer()->get(AuthenticatedCacheContext::class);

        $tokenStorage->setToken(null);
        $this->assertEquals('anon', $cacheContext->resolve());

        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));
        $this->assertEquals('auth', $cacheContext->resolve());

        $tokenStorage->setToken(null);
    }

    public function testFragmentsNotVaryingByAuthenticationRenderIdenticallyForEveryVisitor(): void
    {
        $this->provideFragmentTestSet();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');

        $assertedFragments = 0;
        foreach ($this->allFragments() as $fragment) {
            $declaredContexts = $fragment->getCacheability()->getCacheContexts()->toArray();
            if (in_array(AuthenticatedCacheContext::class, $declaredContexts, true)) {
                continue;
            }

            $tokenStorage->setToken(null);
            $renderedForAnonymousVisitor = $fragment->render();

            $tokenStorage->setToken(new UsernamePasswordToken(
                new InMemoryUser('admin', null, ['ROLE_ADMIN']),
                'main',
                ['ROLE_ADMIN'],
            ));
            $renderedForAuthenticatedVisitor = $fragment->render();

            $tokenStorage->setToken(null);
            ++$assertedFragments;

            $this->assertEquals(
                $renderedForAnonymousVisitor,
                $renderedForAuthenticatedVisitor,
                sprintf(
                    'Fragment "%s" renders differently once you are logged in, but does not declare %s. Either declare the context or stop rendering logged-in-only markup.',
                    $fragment->getPath(),
                    AuthenticatedCacheContext::class,
                )
            );
        }

        $this->assertGreaterThan(0, $assertedFragments);
    }
}

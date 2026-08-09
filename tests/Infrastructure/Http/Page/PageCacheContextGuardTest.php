<?php

namespace App\Tests\Infrastructure\Http\Page;

use App\Domain\Activity\ActivityPageResolver;
use App\Domain\Calendar\MonthPageResolver;
use App\Domain\Rewind\RewindComparePageResolver;
use App\Domain\Rewind\RewindPageResolver;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Http\Page\Page;
use App\Infrastructure\Http\Page\PageRegistry;
use App\Infrastructure\Http\Page\PageResolver;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class PageCacheContextGuardTest extends ContainerTestCase
{
    use ProvideTestData;

    /**
     * A resolver serves a family of paths, so these guards need one real path per resolver to work with.
     * A resolver without an entry fails every test in this class, which is the point.
     */
    private const array PATH_PER_RESOLVER = [
        ActivityPageResolver::class => 'activity/activity-9756441741',
        MonthPageResolver::class => 'month/2023-06',
        RewindPageResolver::class => 'rewind/2023',
        RewindComparePageResolver::class => 'rewind/2023/compare/2022',
    ];

    public function testEveryPageHasAPathAndACacheKeyOfItsOwn(): void
    {
        $this->provideFullTestSet();

        $paths = [];
        $cacheKeys = [];
        foreach ($this->allPages() as $page) {
            $paths[] = $page->getPath();
            $cacheKeys[] = $page->getCacheability()->getCacheKey();
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

    public function testEveryContextDeclaredByAPageResolvesThroughTheRealRegistry(): void
    {
        $this->provideFullTestSet();

        /** @var CacheContextRegistry $cacheContextRegistry */
        $cacheContextRegistry = $this->getContainer()->get(CacheContextRegistry::class);

        foreach ($this->allPages() as $page) {
            $this->assertIsString(
                $cacheContextRegistry->buildCacheKeySegments($page->getCacheability()->getCacheContexts())
            );
        }
    }

    /**
     * @return Page[]
     */
    private function allPages(): array
    {
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = $this->getContainer()->get(PageRegistry::class);

        $pages = [];
        foreach ($pageRegistry->all() as $page) {
            if (!$page instanceof PageResolver) {
                $pages[] = $page;
                continue;
            }

            $path = self::PATH_PER_RESOLVER[$page::class] ?? null;
            $this->assertNotNull($path, sprintf('Add a path for "%s" to %s::PATH_PER_RESOLVER.', $page::class, self::class));

            $resolvedPage = $page->resolve($path);
            $this->assertNotNull($resolvedPage, sprintf('"%s" does not resolve "%s" anymore, pick another path.', $page::class, $path));

            $pages[] = $resolvedPage;
        }

        return $pages;
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

    public function testPagesNotVaryingByAuthenticationRenderIdenticallyForEveryVisitor(): void
    {
        $this->provideFullTestSet();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');

        $assertedPages = 0;
        foreach ($this->allPages() as $page) {
            $declaredContexts = $page->getCacheability()->getCacheContexts()->toArray();
            if (in_array(AuthenticatedCacheContext::class, $declaredContexts, true)) {
                continue;
            }

            $tokenStorage->setToken(null);
            $renderedForAnonymousVisitor = $page->render();

            $tokenStorage->setToken(new UsernamePasswordToken(
                new InMemoryUser('admin', null, ['ROLE_ADMIN']),
                'main',
                ['ROLE_ADMIN'],
            ));
            $renderedForAuthenticatedVisitor = $page->render();

            $tokenStorage->setToken(null);
            ++$assertedPages;

            $this->assertEquals(
                $renderedForAnonymousVisitor,
                $renderedForAuthenticatedVisitor,
                sprintf(
                    'Page "%s" renders differently once you are logged in, but does not declare %s. Either declare the context or stop rendering logged-in-only markup.',
                    $page->getPath(),
                    AuthenticatedCacheContext::class,
                )
            );
        }

        $this->assertGreaterThan(0, $assertedPages);
    }
}

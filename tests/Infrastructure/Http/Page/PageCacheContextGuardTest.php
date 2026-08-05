<?php

namespace App\Tests\Infrastructure\Http\Page;

use App\Infrastructure\Cache\CacheContextRegistry;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Http\Page\PageRegistry;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class PageCacheContextGuardTest extends ContainerTestCase
{
    use ProvideTestData;

    public function testEveryPageHasAPathAndACacheKeyOfItsOwn(): void
    {
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = $this->getContainer()->get(PageRegistry::class);

        $paths = [];
        $cacheKeys = [];
        foreach ($pageRegistry->all() as $page) {
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
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = $this->getContainer()->get(PageRegistry::class);
        /** @var CacheContextRegistry $cacheContextRegistry */
        $cacheContextRegistry = $this->getContainer()->get(CacheContextRegistry::class);

        foreach ($pageRegistry->all() as $page) {
            $this->assertIsString(
                $cacheContextRegistry->buildCacheKeySegments($page->getCacheability()->getCacheContexts())
            );
        }
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
        /** @var PageRegistry $pageRegistry */
        $pageRegistry = $this->getContainer()->get(PageRegistry::class);

        $assertedPages = 0;
        foreach ($pageRegistry->all() as $page) {
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

<?php

namespace App\Tests\Infrastructure\Http\Fragment;

use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentRegistry;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class FragmentCacheContextGuardTest extends ContainerTestCase
{
    use ProvideTestData;

    public function testEveryFragmentHasAPathAndACacheKeyOfItsOwn(): void
    {
        $this->provideFragmentTestSet();

        $paths = [];
        $cacheKeys = [];
        foreach ($this->allFragments() as $fragment) {
            $paths[] = $fragment->getPath();
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
            $this->assertInstanceOf(Fragment::class, $fragment);
            $fragments[] = $fragment;
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

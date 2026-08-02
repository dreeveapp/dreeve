<?php

namespace App\Tests\Infrastructure\Http\Page;

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

    // Cache contexts are opt-in, which is only safe as long as a page that does not declare one really
    // does render the same for everybody. Without this, a template tweak silently serves an admin
    // flavoured page to anonymous visitors, cached under the anonymous key.
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

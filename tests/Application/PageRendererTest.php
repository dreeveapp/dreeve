<?php

namespace App\Tests\Application;

use App\Application\PageRenderer;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;

class PageRendererTest extends ContainerTestCase
{
    use ProvideTestData;

    public function testItWrapsTheRenderInTheAppShell(): void
    {
        $this->provideFullTestSet();

        $response = $this->getContainer()->get(PageRenderer::class)->render(
            cacheability: Cacheability::for('page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            render: fn (): string => '<p>The page content</p>',
            navigationSection: null,
        );

        $this->assertStringContainsString('<p>The page content</p>', (string) $response->getContent());
        $this->assertStringContainsString('</html>', (string) $response->getContent());
        $this->assertNotNull($response->headers->get('X-Dreeve-Cache-Key'));
    }

    public function testItLetsTheBrowserStoreAPageWithoutCacheContexts(): void
    {
        $this->provideFullTestSet();

        $response = $this->getContainer()->get(PageRenderer::class)->render(
            cacheability: Cacheability::for('page', CacheTags::of(RootCacheTag::ACTIVITIES)),
            render: fn (): string => '',
            navigationSection: null,
        );

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testItNeverLetsTheBrowserStoreAPageWithCacheContexts(): void
    {
        $this->provideFullTestSet();

        $response = $this->getContainer()->get(PageRenderer::class)->render(
            cacheability: Cacheability::for(
                cacheKey: 'page',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => '',
            navigationSection: null,
        );

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}

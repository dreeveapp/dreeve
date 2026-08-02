<?php

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\CacheContextRegistry;
use App\Infrastructure\Cache\CacheContexts;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Config\DemoMode;
use App\Infrastructure\Security\TrustedVisitor;
use App\Tests\ContainerTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class CacheableRendererTest extends ContainerTestCase
{
    private RenderCache $renderCache;
    private CacheableRenderer $cacheableRenderer;

    public function testItRendersOnceAndServesEveryRequestAfterThatFromCache(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));

        $this->assertEquals('rendered', $this->cacheableRenderer->render($cacheable)->content);

        $cacheable->rendered = 'changed';
        $this->assertEquals('rendered', $this->cacheableRenderer->render($cacheable)->content);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItRendersAgainAfterItsTagWasInvalidated(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->invalidateTags(CacheTag::ACTIVITIES);

        $cacheable->rendered = 'changed';
        $this->assertEquals('changed', $this->cacheableRenderer->render($cacheable)->content);
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItKeepsTheEntryWhenAnUnrelatedTagWasInvalidated(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->invalidateTags(CacheTag::SETTINGS);

        $cacheable->rendered = 'changed';
        $this->assertEquals('rendered', $this->cacheableRenderer->render($cacheable)->content);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItCachesARenderThatIsNull(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));
        $cacheable->rendered = null;

        $this->assertNull($this->cacheableRenderer->render($cacheable)->content);
        $this->assertNull($this->cacheableRenderer->render($cacheable)->content);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItRendersEveryTimeWhenThereIsNoCacheability(): void
    {
        $cacheable = CacheableStub::for(Cacheability::none());

        $this->cacheableRenderer->render($cacheable);
        $this->cacheableRenderer->render($cacheable);

        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItRendersAgainAfterTheWholeCacheWasCleared(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->clear();

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItReportsWhetherTheRenderCameFromCache(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of(CacheTag::ACTIVITIES)));

        $this->assertFalse($this->cacheableRenderer->render($cacheable)->wasServedFromCache);
        $this->assertTrue($this->cacheableRenderer->render($cacheable)->wasServedFromCache);
    }

    public function testItNeverReportsAnUncacheableRenderAsComingFromCache(): void
    {
        $cacheable = CacheableStub::for(Cacheability::none());

        $this->assertFalse($this->cacheableRenderer->render($cacheable)->wasServedFromCache);
        $this->assertFalse($this->cacheableRenderer->render($cacheable)->wasServedFromCache);
    }

    public function testItKeepsOneEntryPerContextValueAndNeverCrossesThemOver(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheTags: CacheTags::of(CacheTag::ACTIVITIES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $cacheable->rendered = 'anonymized-html';
        $this->assertEquals(
            'anonymized-html',
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheable)->content
        );

        $cacheable->rendered = 'trusted-html';
        $this->assertEquals(
            'trusted-html',
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheable)->content
        );

        $cacheable->rendered = 'should-never-be-rendered';
        $this->assertEquals(
            'anonymized-html',
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheable)->content
        );
        $this->assertEquals(
            'trusted-html',
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheable)->content
        );
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItCollapsesToASingleEntryWhenDemoModeIsDisabled(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheTags: CacheTags::of(CacheTag::ACTIVITIES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $this->rendererFor(demoModeIsEnabled: false, loggedIn: false)->render($cacheable);
        $this->rendererFor(demoModeIsEnabled: false, loggedIn: true)->render($cacheable);

        // Everybody is trusted, so there is nothing to vary by and no entry to waste.
        $this->assertEquals(1, $cacheable->renderCount);
    }

    private function rendererFor(bool $demoModeIsEnabled, bool $loggedIn): CacheableRenderer
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($loggedIn ? $this->createStub(UserInterface::class) : null);

        return new CacheableRenderer(
            $this->renderCache,
            new CacheContextRegistry([
                new TrustedVisitorCacheContext(new TrustedVisitor(
                    DemoMode::fromString($demoModeIsEnabled ? '1' : '0'),
                    $security,
                )),
            ]),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
        $this->cacheableRenderer = new CacheableRenderer(
            $this->renderCache,
            new CacheContextRegistry([]),
        );
    }
}

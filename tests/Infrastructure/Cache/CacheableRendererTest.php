<?php

namespace App\Tests\Infrastructure\Cache;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Context\TrustedVisitorCacheContext;
use App\Infrastructure\Cache\Render\Render;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Config\DemoMode;
use App\Infrastructure\Security\TrustedVisitor;
use App\Tests\ContainerTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class CacheableRendererTest extends ContainerTestCase
{
    private const array CACHE_TAGS = ['settings.appearance', 'settings.general', 'activity.images'];
    private RenderCache $renderCache;
    private CacheableRenderer $cacheableRenderer;

    public function testItRendersOnceAndServesEveryRequestAfterThatFromCache(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );

        $cacheable->rendered = 'changed';
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItRendersAgainAfterItsTagWasInvalidated(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->invalidateTags(RootCacheTag::ACTIVITY_IMAGES);

        $cacheable->rendered = 'changed';
        $this->assertEquals(
            Render::freshlyRendered('changed', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItKeepsTheEntryWhenAnUnrelatedTagWasInvalidated(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->invalidateTags(RootCacheTag::CHALLENGES);

        $cacheable->rendered = 'changed';
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItCachesARenderThatIsNull(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));
        $cacheable->rendered = null;

        $this->assertEquals(
            Render::freshlyRendered(null, AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(
            Render::servedFromCache(null, AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItRendersAgainAfterTheWholeCacheWasCleared(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));
        $this->cacheableRenderer->render($cacheable);

        $this->renderCache->clear();

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItReportsWhetherTheRenderCameFromCache(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)));

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheable)
        );
    }

    public function testItReportsTheCacheKeyIncludingItsContextSegments(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheable)
        );
    }

    public function testItKeepsOneEntryPerContextValueAndNeverCrossesThemOver(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $cacheable->rendered = 'anonymized-html';
        $this->assertEquals(
            Render::freshlyRendered('anonymized-html', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheable)
        );

        $cacheable->rendered = 'trusted-html';
        $this->assertEquals(
            Render::freshlyRendered('trusted-html', AppVersion::getSemanticVersion().'.stub.trust=trusted', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheable)
        );

        $cacheable->rendered = 'should-never-be-rendered';
        $this->assertEquals(
            Render::servedFromCache('anonymized-html', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheable)
        );
        $this->assertEquals(
            Render::servedFromCache('trusted-html', AppVersion::getSemanticVersion().'.stub.trust=trusted', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheable)
        );
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItCollapsesToASingleEntryWhenDemoModeIsDisabled(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $this->rendererFor(demoModeIsEnabled: false, loggedIn: false)->render($cacheable);
        $this->rendererFor(demoModeIsEnabled: false, loggedIn: true)->render($cacheable);

        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItFailsWhenTheDeclaredContextIsNotRegistered(): void
    {
        $cacheable = CacheableStub::for(Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        ));

        $this->expectExceptionObject(new \RuntimeException(sprintf(
            'Cache context "%s" is not registered',
            TrustedVisitorCacheContext::class
        )));
        $this->cacheableRenderer->render($cacheable);
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

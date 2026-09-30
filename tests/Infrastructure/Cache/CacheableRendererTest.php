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
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );

        $render->rendered = 'changed';
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(1, $render->renderCount);
    }

    public function testItRendersAgainAfterItsTagWasInvalidated(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();
        $this->cacheableRenderer->render($cacheability, $render(...));

        $this->renderCache->invalidateTags(RootCacheTag::ACTIVITY_IMAGES);

        $render->rendered = 'changed';
        $this->assertEquals(
            Render::freshlyRendered('changed', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(2, $render->renderCount);
    }

    public function testItKeepsTheEntryWhenAnUnrelatedTagWasInvalidated(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();
        $this->cacheableRenderer->render($cacheability, $render(...));

        $this->renderCache->invalidateTags(RootCacheTag::CHALLENGES);

        $render->rendered = 'changed';
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(1, $render->renderCount);
    }

    public function testItCachesARenderThatIsNull(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();
        $render->rendered = null;

        $this->assertEquals(
            Render::freshlyRendered(null, AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(
            Render::servedFromCache(null, AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(1, $render->renderCount);
    }

    public function testItRendersAgainAfterTheWholeCacheWasCleared(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();
        $this->cacheableRenderer->render($cacheability, $render(...));

        $this->renderCache->clear();

        $this->cacheableRenderer->render($cacheability, $render(...));
        $this->assertEquals(2, $render->renderCount);
    }

    public function testItReportsWhetherTheRenderCameFromCache(): void
    {
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));
        $render = new RenderStub();

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
        $this->assertEquals(
            Render::servedFromCache('rendered', AppVersion::getSemanticVersion().'.stub', self::CACHE_TAGS),
            $this->cacheableRenderer->render($cacheability, $render(...))
        );
    }

    public function testItReportsTheCacheKeyIncludingItsContextSegments(): void
    {
        $cacheability = Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
        $render = new RenderStub();

        $this->assertEquals(
            Render::freshlyRendered('rendered', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheability, $render(...))
        );
    }

    public function testItKeepsOneEntryPerContextValueAndNeverCrossesThemOver(): void
    {
        $cacheability = Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
        $render = new RenderStub();

        $render->rendered = 'anonymized-html';
        $this->assertEquals(
            Render::freshlyRendered('anonymized-html', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheability, $render(...))
        );

        $render->rendered = 'trusted-html';
        $this->assertEquals(
            Render::freshlyRendered('trusted-html', AppVersion::getSemanticVersion().'.stub.trust=trusted', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheability, $render(...))
        );

        $render->rendered = 'should-never-be-rendered';
        $this->assertEquals(
            Render::servedFromCache('anonymized-html', AppVersion::getSemanticVersion().'.stub.trust=anonymized', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: false)->render($cacheability, $render(...))
        );
        $this->assertEquals(
            Render::servedFromCache('trusted-html', AppVersion::getSemanticVersion().'.stub.trust=trusted', self::CACHE_TAGS),
            $this->rendererFor(demoModeIsEnabled: true, loggedIn: true)->render($cacheability, $render(...))
        );
        $this->assertEquals(2, $render->renderCount);
    }

    public function testItCollapsesToASingleEntryWhenDemoModeIsDisabled(): void
    {
        $cacheability = Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
        $render = new RenderStub();

        $this->rendererFor(demoModeIsEnabled: false, loggedIn: false)->render($cacheability, $render(...));
        $this->rendererFor(demoModeIsEnabled: false, loggedIn: true)->render($cacheability, $render(...));

        $this->assertEquals(1, $render->renderCount);
    }

    public function testItFailsWhenTheDeclaredContextIsNotRegistered(): void
    {
        $cacheability = Cacheability::for(
            cacheKey: 'stub',
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
            cacheContexts: CacheContexts::of(TrustedVisitorCacheContext::class),
        );
        $render = new RenderStub();

        $this->expectExceptionObject(new \RuntimeException(sprintf(
            'Cache context "%s" is not registered',
            TrustedVisitorCacheContext::class
        )));
        $this->cacheableRenderer->render($cacheability, $render(...));
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

<?php

namespace App\Tests\Infrastructure\Cache;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Render;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;

class RenderCacheTest extends ContainerTestCase
{
    private const int SELF_HEALING_TTL_IN_SECONDS = 86400;

    private RenderCache $renderCache;

    public function testItStripsReservedCharactersOutOfTheCacheKey(): void
    {
        $render = $this->renderCache->get(
            cacheKey: 'activity/123.tz=Europe/Brussels.user=me@example.com',
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->assertEquals(
            Render::freshlyRendered(
                'rendered',
                AppVersion::getSemanticVersion().'.activity_123.tz=Europe_Brussels.user=me_example.com',
                ['settings.appearance', 'settings.general', 'activity.images'],
                self::SELF_HEALING_TTL_IN_SECONDS,
            ),
            $render
        );
    }

    public function testAKeyThatNeededNormalisingStillServesFromCache(): void
    {
        $cacheKey = 'segment/9:special';
        $cacheability = Cacheability::for('stub', CacheTags::of(CacheTag::ACTIVITY_IMAGES));

        $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'rendered');
        $render = $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'should-not-run');

        $this->assertEquals(
            Render::servedFromCache(
                'rendered',
                AppVersion::getSemanticVersion().'.segment_9_special',
                ['settings.appearance', 'settings.general', 'activity.images'],
                self::SELF_HEALING_TTL_IN_SECONDS,
            ),
            $render
        );
    }

    public function testItReportsTheDeclaredLifetimeOnAMiss(): void
    {
        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(
                cacheKey: 'stub',
                cacheTags: CacheTags::of(CacheTag::ACTIVITY_IMAGES),
                ttlInSeconds: 60,
            ),
            callback: fn (): string => 'rendered',
        );

        $this->assertEquals(60, $render->getTtlInSeconds());
    }

    public function testItReportsWhatWasStoredOnAHitAndNotWhatIsDeclaredNow(): void
    {
        $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(
                cacheKey: 'stub',
                cacheTags: CacheTags::of(CacheTag::ACTIVITY_IMAGES),
                ttlInSeconds: 3600,
            ),
            callback: fn (): string => 'rendered',
        );

        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(
                cacheKey: 'stub',
                cacheTags: CacheTags::of(CacheTag::CHALLENGES),
                ttlInSeconds: 60,
            ),
            callback: fn (): string => 'should-not-run',
        );

        $this->assertEquals(3600, $render->getTtlInSeconds());
        $this->assertEquals(
            ['settings.appearance', 'settings.general', 'activity.images'],
            $render->getCacheTags()
        );
    }

    public function testItPrunes(): void
    {
        $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->renderCache->prune();

        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'should-not-run',
        );

        $this->assertEquals(
            Render::servedFromCache(
                'rendered',
                AppVersion::getSemanticVersion().'.photos',
                ['settings.appearance', 'settings.general', 'activity.images'],
                self::SELF_HEALING_TTL_IN_SECONDS,
            ),
            $render
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

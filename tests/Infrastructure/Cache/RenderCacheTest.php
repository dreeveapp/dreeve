<?php

namespace App\Tests\Infrastructure\Cache;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Render;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\RootCacheTag;
use App\Tests\ContainerTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class RenderCacheTest extends ContainerTestCase
{
    private RenderCache $renderCache;

    public function testItStripsReservedCharactersOutOfTheCacheKey(): void
    {
        $render = $this->renderCache->get(
            cacheKey: 'activity/123.tz=Europe/Brussels.user=me@example.com',
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->assertEquals(
            Render::freshlyRendered(
                'rendered',
                AppVersion::getSemanticVersion().'.activity_123.tz=Europe_Brussels.user=me_example.com',
                ['settings.appearance', 'settings.general', 'activity.images'],
            ),
            $render
        );
    }

    public function testAKeyThatNeededNormalisingStillServesFromCache(): void
    {
        $cacheKey = 'segment/9:special';
        $cacheability = Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES));

        $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'rendered');
        $render = $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'should-not-run');

        $this->assertEquals(
            Render::servedFromCache(
                'rendered',
                AppVersion::getSemanticVersion().'.segment_9_special',
                ['settings.appearance', 'settings.general', 'activity.images'],
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
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
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
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITY_IMAGES),
                ttlInSeconds: 3600,
            ),
            callback: fn (): string => 'rendered',
        );

        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(
                cacheKey: 'stub',
                cacheTags: CacheTags::of(RootCacheTag::CHALLENGES),
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

    public function testItReportsNoTtlForAnItemThatWasStoredWithoutAnExpiry(): void
    {
        $pool = new TagAwareAdapter(new FilesystemAdapter(
            namespace: 'render-cache-test',
            defaultLifetime: 0,
            directory: sys_get_temp_dir(),
        ));
        $pool->clear();

        $item = $pool->getItem(AppVersion::getSemanticVersion().'.stored-without-an-expiry');
        $item->set('rendered');
        $item->tag(['activity.images']);
        $pool->save($item);

        $storedExpiry = $pool->getItem(AppVersion::getSemanticVersion().'.stored-without-an-expiry')
            ->getMetadata()[ItemInterface::METADATA_EXPIRY] ?? null;
        $this->assertGreaterThan(50 * 365 * 86400, $storedExpiry - time());

        $render = new RenderCache($pool)->get(
            cacheKey: 'stored-without-an-expiry',
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'should-not-run',
        );

        $this->assertTrue($render->wasServedFromCache());
        $this->assertNull($render->getTtlInSeconds());
        $this->assertArrayNotHasKey('X-Cache-TTL', $render->getCacheHeaders());

        $pool->clear();
    }

    public function testItPrunes(): void
    {
        $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->renderCache->prune();

        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'should-not-run',
        );

        $this->assertEquals(
            Render::servedFromCache(
                'rendered',
                AppVersion::getSemanticVersion().'.photos',
                ['settings.appearance', 'settings.general', 'activity.images'],
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

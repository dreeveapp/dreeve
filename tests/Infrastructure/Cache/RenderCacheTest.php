<?php

namespace App\Tests\Infrastructure\Cache;

use App\Application\AppVersion;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;

class RenderCacheTest extends ContainerTestCase
{
    private RenderCache $renderCache;

    public function testItStripsReservedCharactersOutOfTheCacheKey(): void
    {
        $render = $this->renderCache->get(
            cacheKey: 'activity/123.tz=Europe/Brussels.user=me@example.com',
            cacheability: Cacheability::for(CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->assertEquals(
            AppVersion::getSemanticVersion().'.activity_123.tz=Europe_Brussels.user=me_example.com',
            $render->cacheKey
        );
        $this->assertEquals('rendered', $render->content);
    }

    public function testAKeyThatNeededNormalisingStillServesFromCache(): void
    {
        $cacheKey = 'segment/9:special';
        $cacheability = Cacheability::for(CacheTags::of(CacheTag::ACTIVITY_IMAGES));

        $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'rendered');
        $render = $this->renderCache->get($cacheKey, $cacheability, fn (): string => 'should-not-run');

        $this->assertTrue($render->wasServedFromCache);
        $this->assertEquals('rendered', $render->content);
    }

    public function testItPrunes(): void
    {
        $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'rendered',
        );

        $this->renderCache->prune();

        $render = $this->renderCache->get(
            cacheKey: 'photos',
            cacheability: Cacheability::for(CacheTags::of(CacheTag::ACTIVITY_IMAGES)),
            callback: fn (): string => 'should-not-run',
        );
        $this->assertTrue($render->wasServedFromCache);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

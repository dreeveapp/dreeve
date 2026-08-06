<?php

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheStatus;
use App\Infrastructure\Cache\Render;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase
{
    public function testGetCacheHeadersForAHit(): void
    {
        $render = Render::servedFromCache(
            '<html lang="en"></html>',
            'v5.1.5.best-efforts',
            ['activities', 'settings.appearance', 'settings.general'],
            19784,
        );

        $this->assertEquals(CacheStatus::HIT, $render->getCacheStatus());
        $this->assertTrue($render->wasServedFromCache());
        $this->assertEquals(
            [
                'X-Cache' => 'HIT',
                'X-Cache-Key' => 'v5.1.5.best-efforts',
                'X-Cache-Tags' => 'activities, settings.appearance, settings.general',
                'X-Cache-TTL' => '19784',
            ],
            $render->getCacheHeaders()
        );
    }

    public function testGetCacheHeadersForAMiss(): void
    {
        $render = Render::freshlyRendered('<html lang="en"></html>', 'v5.1.5.heatmap', ['activity.route'], 86400);

        $this->assertEquals(CacheStatus::MISS, $render->getCacheStatus());
        $this->assertFalse($render->wasServedFromCache());
        $this->assertEquals(
            [
                'X-Cache' => 'MISS',
                'X-Cache-Key' => 'v5.1.5.heatmap',
                'X-Cache-Tags' => 'activity.route',
                'X-Cache-TTL' => '86400',
            ],
            $render->getCacheHeaders()
        );
    }

    public function testGetCacheHeadersWhenTheRenderIsNotCacheable(): void
    {
        $render = Render::notCacheable('<html lang="en"></html>');

        $this->assertEquals(CacheStatus::UNCACHEABLE, $render->getCacheStatus());
        $this->assertFalse($render->wasServedFromCache());
        $this->assertEquals(['X-Cache' => 'UNCACHEABLE'], $render->getCacheHeaders());
    }

    public function testGetCacheHeadersWhenTheStoredExpiryIsUnknown(): void
    {
        $render = Render::servedFromCache('<html lang="en"></html>', 'v5.1.5.photos', ['activity.images']);

        $this->assertEquals(
            [
                'X-Cache' => 'HIT',
                'X-Cache-Key' => 'v5.1.5.photos',
                'X-Cache-Tags' => 'activity.images',
            ],
            $render->getCacheHeaders()
        );
    }
}

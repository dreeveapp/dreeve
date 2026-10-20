<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayoutRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;

class DashboardInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private DashboardLayoutRepository $dashboardLayoutRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenTheDashboardLayoutIsUpdated(): void
    {
        foreach ([RootCacheTag::DASHBOARD, RootCacheTag::ACTIVITIES] as $cacheTag) {
            $this->renderCache->get(
                cacheKey: $cacheTag->value,
                cacheability: Cacheability::for('stub', CacheTags::of($cacheTag)),
                callback: fn (): string => 'rendered',
            );
        }

        $this->dashboardLayoutRepository->resetToDefault();

        foreach ([RootCacheTag::DASHBOARD->value => false, RootCacheTag::ACTIVITIES->value => true] as $cacheTag => $servedFromCache) {
            $this->assertSame(
                $servedFromCache,
                $this->renderCache->get(
                    cacheKey: $cacheTag,
                    cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::from($cacheTag))),
                    callback: fn (): string => 'rendered',
                )->wasServedFromCache()
            );
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardLayoutRepository = $this->getContainer()->get(DashboardLayoutRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

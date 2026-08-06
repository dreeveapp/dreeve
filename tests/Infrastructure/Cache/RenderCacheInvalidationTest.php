<?php

namespace App\Tests\Infrastructure\Cache;

use App\Application\IndexPage;
use App\Domain\Activity\Eddington\EddingtonPage;
use App\Domain\Activity\Image\PhotosPage;
use App\Domain\Activity\Route\HeatmapPage;
use App\Domain\Challenge\ChallengesPage;
use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RenderCacheInvalidationTest extends ContainerTestCase
{
    private RenderCache $renderCache;

    #[DataProvider('provideCacheables')]
    public function testItIsInvalidatedByItsCacheTags(string $cacheableClassName, array $invalidatingCacheTags): void
    {
        /** @var Cacheable $cacheable */
        $cacheable = $this->getContainer()->get($cacheableClassName);
        $cacheability = $cacheable->getCacheability();

        foreach (CacheTag::cases() as $cacheTag) {
            $this->renderCache->clear();
            $this->renderCache->get(
                cacheKey: $cacheability->getCacheKey(),
                cacheability: $cacheability,
                callback: fn (): string => 'rendered',
            );

            $this->renderCache->invalidateTags($cacheTag);

            $this->assertEquals(
                !in_array($cacheTag, $invalidatingCacheTags, true),
                $this->renderCache->get(
                    cacheKey: $cacheability->getCacheKey(),
                    cacheability: $cacheability,
                    callback: fn (): string => 'rendered',
                )->wasServedFromCache(),
                sprintf('Invalidating "%s" affected the "%s" render', $cacheTag->value, $cacheability->getCacheKey())
            );
        }
    }

    public static function provideCacheables(): \Generator
    {
        yield 'index' => [IndexPage::class, [
            CacheTag::SETTINGS_APPEARANCE,
            CacheTag::SETTINGS_GENERAL,
            CacheTag::APP_BUILD,
            CacheTag::ACTIVITIES,
            CacheTag::ACTIVITY_IMAGES,
            CacheTag::CHALLENGES,
            CacheTag::SETTINGS_INTEGRATIONS,
            CacheTag::SETTINGS_MAPS,
        ]];

        yield 'challenges' => [ChallengesPage::class, [
            CacheTag::SETTINGS_APPEARANCE,
            CacheTag::SETTINGS_GENERAL,
            CacheTag::CHALLENGES,
        ]];

        yield 'eddington' => [EddingtonPage::class, [
            CacheTag::SETTINGS_APPEARANCE,
            CacheTag::SETTINGS_GENERAL,
            CacheTag::ACTIVITIES,
            CacheTag::SETTINGS_METRICS,
        ]];

        yield 'heatmap' => [HeatmapPage::class, [
            CacheTag::SETTINGS_APPEARANCE,
            CacheTag::SETTINGS_GENERAL,
            CacheTag::ACTIVITY_ROUTE,
            CacheTag::SETTINGS_MAPS,
        ]];

        yield 'photos' => [PhotosPage::class, [
            CacheTag::SETTINGS_APPEARANCE,
            CacheTag::SETTINGS_GENERAL,
            CacheTag::ACTIVITY_IMAGES,
        ]];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

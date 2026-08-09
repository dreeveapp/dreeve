<?php

namespace App\Tests\Infrastructure\Cache\Render;

use App\Application\IndexPage;
use App\Domain\Activity\Eddington\EddingtonPage;
use App\Domain\Activity\Image\PhotosPage;
use App\Domain\Activity\Route\HeatmapPage;
use App\Domain\Challenge\ChallengesPage;
use App\Domain\Milestone\MilestonesPage;
use App\Domain\Rewind\RewindComparePageResolver;
use App\Domain\Rewind\RewindPageResolver;
use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Page\PageResolver;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class RenderCacheInvalidationTest extends ContainerTestCase
{
    use ProvideTestData;

    private RenderCache $renderCache;

    #[DataProvider('provideCacheables')]
    public function testItIsInvalidatedByItsCacheTags(string $cacheableClassName, array $invalidatingCacheTags, ?string $pathToResolve = null): void
    {
        /** @var Cacheable $cacheable */
        $cacheable = $this->getContainer()->get($cacheableClassName);

        if ($cacheable instanceof PageResolver) {
            $this->provideFullTestSet();
            $cacheable = $cacheable->resolve((string) $pathToResolve);
            $this->assertNotNull($cacheable);
        }

        $cacheability = $cacheable->getCacheability();

        foreach (RootCacheTag::cases() as $cacheTag) {
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
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::APP_BUILD,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::CHALLENGES,
            RootCacheTag::SETTINGS_INTEGRATIONS,
            RootCacheTag::SETTINGS_MAPS,
        ]];

        yield 'challenges' => [ChallengesPage::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::CHALLENGES,
        ]];

        yield 'eddington' => [EddingtonPage::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::SETTINGS_METRICS,
        ]];

        yield 'heatmap' => [HeatmapPage::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITY_ROUTE,
            RootCacheTag::SETTINGS_MAPS,
        ]];

        yield 'milestones' => [MilestonesPage::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::GEAR,
            RootCacheTag::SETTINGS_METRICS,
        ]];

        // The year scoped rewinds cannot be expressed here, they are covered by
        // ActivityInvalidateCacheTagsListenerTest and the rewind page tests.
        yield 'rewind' => [RewindPageResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::GEAR,
        ], 'rewind/all-time'];

        // Only the all time side contributes unscoped tags, the 2023 side is year scoped and therefore
        // untouched by the root tags this test invalidates.
        yield 'rewind-compare' => [RewindComparePageResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::GEAR,
        ], 'rewind/all-time/compare/2023'];

        yield 'photos' => [PhotosPage::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITY_IMAGES,
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

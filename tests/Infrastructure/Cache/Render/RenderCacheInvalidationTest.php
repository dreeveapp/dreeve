<?php

namespace App\Tests\Infrastructure\Cache\Render;

use App\Application\IndexPage;
use App\Domain\Activity\BestEffort\ActivityBestEffortsFragmentResolver;
use App\Domain\Activity\Eddington\EddingtonFragment;
use App\Domain\Activity\Image\PhotosFragment;
use App\Domain\Activity\Route\HeatmapFragment;
use App\Domain\Challenge\ChallengesFragment;
use App\Domain\Gear\GearStatsFragment;
use App\Domain\Gear\Maintenance\GearMaintenanceDueFragment;
use App\Domain\Gear\Maintenance\GearMaintenanceFragment;
use App\Domain\Gear\RecordingDevice\RecordingDevicesFragment;
use App\Domain\Milestone\MilestonesFragment;
use App\Domain\Rewind\RewindCompareFragmentResolver;
use App\Domain\Rewind\RewindFragmentResolver;
use App\Domain\Segment\ActivitySegmentsFragmentResolver;
use App\Domain\Segment\SegmentDataTableFragment;
use App\Domain\Segment\SegmentsFragment;
use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
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

        if ($cacheable instanceof FragmentResolver) {
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
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::CHALLENGES,
            RootCacheTag::SETTINGS_INTEGRATIONS,
            RootCacheTag::SETTINGS_MAPS,
        ]];

        yield 'challenges' => [ChallengesFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::CHALLENGES,
        ]];

        yield 'eddington' => [EddingtonFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::SETTINGS_METRICS,
        ]];

        yield 'heatmap' => [HeatmapFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITY_ROUTE,
            RootCacheTag::SETTINGS_MAPS,
        ]];

        yield 'milestones' => [MilestonesFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::GEAR,
            RootCacheTag::SETTINGS_METRICS,
        ]];

        // The year scoped rewinds cannot be expressed here, they are covered by
        // ActivityInvalidateCacheTagsListenerTest and the rewind page tests.
        yield 'rewind' => [RewindFragmentResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::GEAR,
        ], 'rewind/all-time'];

        // Only the all time side contributes unscoped tags, the 2023 side is year scoped and therefore
        // untouched by the root tags this test invalidates.
        yield 'rewind-compare' => [RewindCompareFragmentResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::GEAR,
        ], 'rewind/all-time/compare/2023'];

        yield 'photos' => [PhotosFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITY_IMAGES,
        ]];

        yield 'segments' => [SegmentsFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::SEGMENTS,
            RootCacheTag::ACTIVITIES,
        ]];

        yield 'segment-data-table' => [SegmentDataTableFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::SEGMENTS,
        ]];

        yield 'gear' => [GearStatsFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::GEAR,
            RootCacheTag::ACTIVITIES,
        ]];

        yield 'gear-maintenance' => [GearMaintenanceFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::GEAR_MAINTENANCE,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::GEAR,
        ]];

        yield 'gear-maintenance-due' => [GearMaintenanceDueFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::GEAR_MAINTENANCE,
            RootCacheTag::ACTIVITIES,
            RootCacheTag::GEAR,
        ]];

        yield 'gear-recording-devices' => [RecordingDevicesFragment::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::RECORDING_DEVICES,
            RootCacheTag::ACTIVITIES,
        ]];

        yield 'activity-segments' => [ActivitySegmentsFragmentResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::SEGMENTS,
        ], 'activity/activity-9542782314/segments'];

        yield 'activity-best-efforts' => [ActivityBestEffortsFragmentResolver::class, [
            RootCacheTag::SETTINGS_APPEARANCE,
            RootCacheTag::SETTINGS_GENERAL,
            RootCacheTag::ACTIVITIES,
        ], 'activity/activity-9542782314/best-efforts'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

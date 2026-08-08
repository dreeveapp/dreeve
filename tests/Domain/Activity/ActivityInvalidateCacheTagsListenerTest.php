<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\ScopedCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use App\Tests\ContainerTestCase;

class ActivityInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenAnActivityIsAdded(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->buildAsNewlyCreated(),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
    }

    public function testItDoesNotInvalidateWhenAnActivityIsMerelyHydratedAndStored(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES));
    }

    public function testItInvalidatesWhenAnActivityIsDeleted(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
    }

    public function testItInvalidatesTheRouteWhenTheRouteHasBeenUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline('tqafAua~y^vG{D')
            ->withRouteGeography(RouteGeography::create(['country_code' => 'BE']))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withPolyline('kqafAua~y^vG{D'),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
    }

    public function testItInvalidatesWhenAnActivityIsUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withName(ActivityName::fromString('Renamed')),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
    }

    public function testItDoesNotInvalidateWhenAnUpdateRewritesTheSameValues(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withName(ActivityName::fromString('Test activity')),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES));
    }

    public function testItOnlyInvalidatesTheYearAnUpdatedActivityBelongsTo(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withName(ActivityName::fromString('Renamed')),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
    }

    public function testItInvalidatesBothYearsWhenAnActivityMovesToAnotherYear(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withStartDateTime(SerializableDateTime::fromString('2016-10-10')),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
    }

    public function testItDoesNotInvalidateTheRouteWhenTheActivityIsNotOnTheMap(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withPolyline('tqafAua~y^vG{D'),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
    }

    public function testItOnlyInvalidatesTheImagesWhenTheImagesHaveBeenUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withLocalImagePaths(['/image.jpg']),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES));
    }

    public function testItOnlyInvalidatesTheYearAnAddedActivityBelongsTo(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->buildAsNewlyCreated(),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
    }

    public function testItOnlyInvalidatesTheYearADeletedActivityBelongsTo(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016))));
    }

    public function testItOnlyInvalidatesTheYearUpdatedImagesBelongTo(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withLocalImagePaths(['/image.jpg']),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016))));
    }

    public function testItOnlyInvalidatesTheActivityThatWasUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::GRAVEL_RIDE),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(ActivityCacheTag::for($activity->getId())));
        $this->assertTrue($this->isServedFromCache(ActivityCacheTag::for(ActivityId::fromUnprefixed('1'))));
    }

    public function testItInvalidatesTheActivityScopedTagForAnActivityThatIsNotOnTheMap(): void
    {
        // The route signature is null for these, so ActivityRouteWasUpdated never fires. They can
        // still have a profile chart, which is styled after the sport type.
        $activity = ActivityBuilder::fromDefaults()
            ->withWorldType(WorldType::ZWIFT)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withSportType(SportType::VIRTUAL_RIDE),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(CacheTag::ACTIVITY_ROUTE));
        $this->assertFalse($this->isServedFromCache(ActivityCacheTag::for($activity->getId())));
    }

    public function testItInvalidatesTheActivityScopedTagWhenAnActivityIsDeleted(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(ActivityCacheTag::for($activity->getId())));
        $this->assertTrue($this->isServedFromCache(ActivityCacheTag::for(ActivityId::fromUnprefixed('1'))));
    }

    private function isServedFromCache(CacheTag|ScopedCacheTag $cacheTag): bool
    {
        return $this->renderCache->get(
            cacheKey: $cacheTag->toTagString(),
            cacheability: Cacheability::for('stub', CacheTags::of($cacheTag)),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    private function warmUpRenderCache(): void
    {
        $cacheTags = [
            CacheTag::ACTIVITIES,
            CacheTag::ACTIVITY_IMAGES,
            CacheTag::ACTIVITY_ROUTE,
            CacheTag::ACTIVITIES->forYear(Year::fromInt(2023)),
            CacheTag::ACTIVITIES->forYear(Year::fromInt(2016)),
            CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023)),
            CacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016)),
            ActivityCacheTag::for(ActivityId::fromUnprefixed('903645')),
            ActivityCacheTag::for(ActivityId::fromUnprefixed('1')),
        ];

        foreach ($cacheTags as $cacheTag) {
            $this->isServedFromCache($cacheTag);
            $this->assertTrue($this->isServedFromCache($cacheTag));
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

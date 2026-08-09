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
use App\Domain\Calendar\Month;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\RootCacheTag;
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
    }

    public function testItDoesNotInvalidateWhenAnActivityIsMerelyHydratedAndStored(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES));
    }

    public function testItInvalidatesWhenAnActivityIsDeleted(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
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

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
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

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES));
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
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

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
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

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES));
    }

    public function testItInvalidatesTheActivityItselfWhenItsImagesHaveBeenUpdated(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withLocalImagePaths(['/image.jpg']),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(ActivityCacheTag::for($activity->getId())));
        $this->assertTrue($this->isServedFromCache(ActivityCacheTag::for(ActivityId::fromUnprefixed('1'))));
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
    }

    public function testItOnlyInvalidatesTheYearADeletedActivityBelongsTo(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2023))));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2016))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016))));
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016))));
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

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_ROUTE));
        $this->assertFalse($this->isServedFromCache(ActivityCacheTag::for($activity->getId())));
    }

    public function testItOnlyInvalidatesTheMonthAnAddedActivityBelongsTo(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
                ->buildAsNewlyCreated(),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10'))));
        // Another month of the same year keeps its render, which is what the month scope buys over the year scope.
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08'))));
    }

    public function testItOnlyInvalidatesTheMonthADeletedActivityBelongsTo(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10'))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08'))));
    }

    public function testItOnlyInvalidatesTheMonthAnUpdatedActivityBelongsTo(): void
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10'))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08'))));
    }

    public function testItInvalidatesBothMonthsWhenAnActivityMovesWithinTheSameYear(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->update(ActivityWithRawData::fromState(
            $activity->withStartDateTime(SerializableDateTime::fromString('2023-08-10')),
            [],
        ));

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10'))));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08'))));
    }

    public function testItInvalidatesBothMonthsWhenAnActivityMovesToAnotherYear(): void
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

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10'))));
        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2016-10'))));
        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08'))));
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

    private function month(string $monthId): Month
    {
        return Month::fromDate(SerializableDateTime::fromString($monthId.'-01'));
    }

    private function isServedFromCache(CacheTag $cacheTag): bool
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
            RootCacheTag::ACTIVITIES,
            RootCacheTag::ACTIVITY_IMAGES,
            RootCacheTag::ACTIVITY_ROUTE,
            RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2023)),
            RootCacheTag::ACTIVITIES->forYear(Year::fromInt(2016)),
            RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2023)),
            RootCacheTag::ACTIVITY_IMAGES->forYear(Year::fromInt(2016)),
            RootCacheTag::ACTIVITIES->forMonth($this->month('2023-10')),
            RootCacheTag::ACTIVITIES->forMonth($this->month('2023-08')),
            RootCacheTag::ACTIVITIES->forMonth($this->month('2016-10')),
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

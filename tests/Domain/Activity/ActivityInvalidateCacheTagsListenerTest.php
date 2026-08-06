<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Render;
use App\Infrastructure\Cache\RenderCache;
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

        $this->assertFalse($this->render(CacheTag::ACTIVITIES)->wasServedFromCache());
        $this->assertTrue($this->render(CacheTag::ACTIVITY_IMAGES)->wasServedFromCache());
        $this->assertTrue($this->render(CacheTag::ACTIVITY_ROUTE)->wasServedFromCache());
    }

    public function testItDoesNotInvalidateWhenAnActivityIsMerelyHydratedAndStored(): void
    {
        $this->warmUpRenderCache();

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->assertTrue($this->render(CacheTag::ACTIVITIES)->wasServedFromCache());
    }

    public function testItInvalidatesWhenAnActivityIsDeleted(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, []));
        $this->warmUpRenderCache();

        $this->activityRepository->delete($activity->getId());

        $this->assertFalse($this->render(CacheTag::ACTIVITIES)->wasServedFromCache());
        $this->assertFalse($this->render(CacheTag::ACTIVITY_IMAGES)->wasServedFromCache());
        $this->assertFalse($this->render(CacheTag::ACTIVITY_ROUTE)->wasServedFromCache());
    }

    public function testItOnlyInvalidatesTheRouteWhenTheRouteHasBeenUpdated(): void
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

        $this->assertTrue($this->render(CacheTag::ACTIVITIES)->wasServedFromCache());
        $this->assertTrue($this->render(CacheTag::ACTIVITY_IMAGES)->wasServedFromCache());
        $this->assertFalse($this->render(CacheTag::ACTIVITY_ROUTE)->wasServedFromCache());
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

        $this->assertTrue($this->render(CacheTag::ACTIVITY_ROUTE)->wasServedFromCache());
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

        $this->assertTrue($this->render(CacheTag::ACTIVITIES)->wasServedFromCache());
        $this->assertFalse($this->render(CacheTag::ACTIVITY_IMAGES)->wasServedFromCache());
    }

    private function warmUpRenderCache(): void
    {
        foreach ([CacheTag::ACTIVITIES, CacheTag::ACTIVITY_IMAGES, CacheTag::ACTIVITY_ROUTE] as $cacheTag) {
            $this->render($cacheTag);
            $this->assertTrue($this->render($cacheTag)->wasServedFromCache());
        }
    }

    private function render(CacheTag $cacheTag): Render
    {
        return $this->renderCache->get(
            cacheKey: $cacheTag->value,
            cacheability: Cacheability::for('stub', CacheTags::of($cacheTag)),
            callback: fn (): string => 'rendered',
        );
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

<?php

namespace App\Tests\Domain\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionCacheTag;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\DbalActivityCollectionRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Eventing\EventBus;
use App\Tests\ContainerTestCase;

class ActivityCollectionInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private DbalActivityCollectionRepository $activityCollectionRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesTheOverviewWhenACollectionWasAdded(): void
    {
        $this->warmUpRenderCache();

        $this->activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build());

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_COLLECTIONS));
        $this->assertTrue($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('2'))));
    }

    public function testItInvalidatesTheCollectionWhenItWasUpdated(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build();
        $this->activityCollectionRepository->add($activityCollection);
        $this->warmUpRenderCache();

        $this->activityCollectionRepository->update($activityCollection->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1')])));

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_COLLECTIONS));
        $this->assertFalse($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('1'))));
        $this->assertTrue($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('2'))));
    }

    public function testItDoesNotInvalidateWhenNothingChanged(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]))
            ->build();
        $this->activityCollectionRepository->add($activityCollection);
        $this->warmUpRenderCache();

        $this->activityCollectionRepository->update($activityCollection
            ->withName('Mallorca')
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('2'), ActivityId::fromUnprefixed('1')])));

        $this->assertTrue($this->isServedFromCache(RootCacheTag::ACTIVITY_COLLECTIONS));
        $this->assertTrue($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('1'))));
    }

    public function testItInvalidatesTheCollectionWhenItWasDeleted(): void
    {
        $activityCollection = ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->build();
        $this->activityCollectionRepository->add($activityCollection);
        $this->warmUpRenderCache();

        $activityCollection->delete();
        $this->activityCollectionRepository->delete($activityCollection);

        $this->assertFalse($this->isServedFromCache(RootCacheTag::ACTIVITY_COLLECTIONS));
        $this->assertFalse($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('1'))));
        $this->assertTrue($this->isServedFromCache(ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('2'))));
    }

    private function warmUpRenderCache(): void
    {
        foreach ([
            RootCacheTag::ACTIVITY_COLLECTIONS,
            ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('1')),
            ActivityCollectionCacheTag::for(ActivityCollectionId::fromUnprefixed('2')),
        ] as $cacheTag) {
            $this->renderCache->get(
                cacheKey: $cacheTag->toTagString(),
                cacheability: Cacheability::for(
                    cacheKey: $cacheTag->toTagString(),
                    cacheTags: CacheTags::of($cacheTag),
                ),
                callback: fn (): string => 'rendered',
            );
        }
    }

    private function isServedFromCache(CacheTag $cacheTag): bool
    {
        return $this->renderCache->get(
            cacheKey: $cacheTag->toTagString(),
            cacheability: Cacheability::for(
                cacheKey: $cacheTag->toTagString(),
                cacheTags: CacheTags::of($cacheTag),
            ),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityCollectionRepository = new DbalActivityCollectionRepository(
            $this->getConnection(),
            $this->getContainer()->get(EventBus::class),
        );
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

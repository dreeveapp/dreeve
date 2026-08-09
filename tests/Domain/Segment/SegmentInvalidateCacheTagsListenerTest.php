<?php

namespace App\Tests\Domain\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentCacheTag;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;

class SegmentInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private SegmentEffortRepository $segmentEffortRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesTheRiddenSegmentWhenAnEffortIsAdded(): void
    {
        $this->warmUpRenderCache();

        $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->buildAsNewlyCreated());

        $this->assertFalse($this->isServedFromCache(RootCacheTag::SEGMENTS));
        $this->assertFalse($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(1))));
        $this->assertTrue($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(2))));
    }

    public function testItDoesNotInvalidateWhenAnEffortIsMerelyHydratedAndStored(): void
    {
        $this->warmUpRenderCache();

        $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed(1))
            ->build());

        $this->assertTrue($this->isServedFromCache(RootCacheTag::SEGMENTS));
        $this->assertTrue($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(1))));
    }

    public function testItInvalidatesEverySegmentTheDeletedEffortsBelongedTo(): void
    {
        foreach ([[1, 1], [2, 2]] as [$effortId, $segmentId]) {
            $this->segmentEffortRepository->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($effortId))
                ->withSegmentId(SegmentId::fromUnprefixed($segmentId))
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->build());
        }
        $this->warmUpRenderCache();

        $this->segmentEffortRepository->deleteForActivity(ActivityId::fromUnprefixed(1));

        $this->assertFalse($this->isServedFromCache(RootCacheTag::SEGMENTS));
        $this->assertFalse($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(1))));
        $this->assertFalse($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(2))));
        $this->assertTrue($this->isServedFromCache(SegmentCacheTag::for(SegmentId::fromUnprefixed(3))));
    }

    private function warmUpRenderCache(): void
    {
        foreach ([
            RootCacheTag::SEGMENTS,
            SegmentCacheTag::for(SegmentId::fromUnprefixed(1)),
            SegmentCacheTag::for(SegmentId::fromUnprefixed(2)),
            SegmentCacheTag::for(SegmentId::fromUnprefixed(3)),
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

        $this->segmentEffortRepository = $this->getContainer()->get(SegmentEffortRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

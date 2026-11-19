<?php

namespace App\Tests\Domain\Media;

use App\Domain\Media\DbalMediaItemRepository;
use App\Domain\Media\MediaItemCacheTag;
use App\Domain\Media\MediaItemId;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTag;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class MediaItemInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private DbalMediaItemRepository $mediaItemRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesTheMediaItemWhenItWasUpdated(): void
    {
        $mediaItem = MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('1'))
            ->build();
        $this->mediaItemRepository->add($mediaItem);
        $this->warmUpRenderCache();

        $this->mediaItemRepository->update($mediaItem
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.2194'),
                longitude: Longitude::fromString('4.4025'),
            ))
            ->withTakenOn(SerializableDateTime::fromString('2025-04-20 10:15:00')));

        $this->assertFalse($this->isServedFromCache(MediaItemCacheTag::for(MediaItemId::fromUnprefixed('1'))));
        $this->assertTrue($this->isServedFromCache(MediaItemCacheTag::for(MediaItemId::fromUnprefixed('2'))));
    }

    private function warmUpRenderCache(): void
    {
        foreach ([
            MediaItemCacheTag::for(MediaItemId::fromUnprefixed('1')),
            MediaItemCacheTag::for(MediaItemId::fromUnprefixed('2')),
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

        $this->mediaItemRepository = new DbalMediaItemRepository(
            $this->getConnection(),
            $this->getContainer()->get(EventBus::class),
        );
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}

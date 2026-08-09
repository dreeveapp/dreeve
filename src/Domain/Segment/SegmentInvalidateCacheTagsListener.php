<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class SegmentInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToSegmentWasAdded(SegmentWasAdded $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::SEGMENTS);
    }

    #[AsEventListener]
    public function reactToSegmentsWereDeleted(SegmentsWereDeleted $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::SEGMENTS);
    }
}

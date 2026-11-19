<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Infrastructure\Cache\Render\RenderCache;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class MediaItemInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToMediaItemWasUpdated(MediaItemWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(MediaItemCacheTag::for($event->getMediaItemId()));
    }
}

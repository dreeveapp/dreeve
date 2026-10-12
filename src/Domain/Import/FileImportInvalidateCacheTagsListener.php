<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Infrastructure\Cache\Render\RenderCache;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class FileImportInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToFileImportWasDeleted(FileImportWasDeleted $event): void
    {
        if (!($activityId = $event->getActivityId()) instanceof ActivityId) {
            return;
        }

        $this->renderCache->invalidateTags(ActivityCacheTag::for($activityId));
    }
}

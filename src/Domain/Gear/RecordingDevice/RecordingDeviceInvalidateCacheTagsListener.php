<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RecordingDeviceInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToRecordingDeviceWasUpdated(RecordingDeviceWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::RECORDING_DEVICES);
    }
}

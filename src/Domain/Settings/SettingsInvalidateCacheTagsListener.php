<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\RenderCache;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class SettingsInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToSettingsWereUpdated(SettingsWereUpdated $event): void
    {
        $this->renderCache->invalidateTags(CacheTag::forSettingsGroup($event->getGroup()));
    }
}

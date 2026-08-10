<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class DashboardInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToDashboardLayoutWasUpdated(DashboardLayoutWasUpdated $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::DASHBOARD);
    }
}

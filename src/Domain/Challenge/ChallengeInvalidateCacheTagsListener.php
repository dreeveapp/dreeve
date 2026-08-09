<?php

declare(strict_types=1);

namespace App\Domain\Challenge;

use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ChallengeInvalidateCacheTagsListener
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    #[AsEventListener]
    public function reactToChallengeWasImported(ChallengeWasImported $event): void
    {
        $this->renderCache->invalidateTags(RootCacheTag::CHALLENGES);
    }
}

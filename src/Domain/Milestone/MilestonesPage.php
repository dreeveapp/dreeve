<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Http\Page\Page;
use Twig\Environment;

final readonly class MilestonesPage implements Page
{
    public function __construct(
        private MilestoneCollector $milestoneCollector,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'milestones';
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(
                CacheTag::ACTIVITIES,
                CacheTag::GEAR,
                // The Eddington milestones depend on the configured sport types.
                CacheTag::SETTINGS_METRICS,
            ),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/milestones.html.twig')->render([
            'milestones' => $this->milestoneCollector->discoverAll(),
        ]);
    }
}

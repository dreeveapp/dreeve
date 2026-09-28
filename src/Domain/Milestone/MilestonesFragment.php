<?php

declare(strict_types=1);

namespace App\Domain\Milestone;

use App\Application\Navigation\HasNavigationSection;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class MilestonesFragment implements Fragment, HasNavigationSection
{
    public function __construct(
        private MilestoneCollector $milestoneCollector,
        private ActivityRepository $activityRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'milestones';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getNavigationSection(): NavigationSection
    {
        return NavigationSection::MILESTONES;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(
                RootCacheTag::ACTIVITIES,
                RootCacheTag::GEAR,
                // The Eddington milestones depend on the configured sport types.
                RootCacheTag::SETTINGS_METRICS,
            ),
        );
    }

    public function render(): string
    {
        $milestones = $this->milestoneCollector->discoverAll();

        return $this->twig->load('html/milestones.html.twig')->render([
            'milestones' => $milestones,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($milestones->getActivityIds())->keyByActivityId(),
        ]);
    }
}

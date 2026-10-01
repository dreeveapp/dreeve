<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Milestone\MilestoneCollector;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class MilestonesRequestHandler
{
    public function __construct(
        private MilestoneCollector $milestoneCollector,
        private ActivityRepository $activityRepository,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/milestones', name: 'milestones', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'milestones',
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITIES,
                    RootCacheTag::GEAR,
                    // The Eddington milestones depend on the configured sport types.
                    RootCacheTag::SETTINGS_METRICS,
                ),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::MILESTONES,
        );
    }

    private function renderFor(): string
    {
        $milestones = $this->milestoneCollector->discoverAll();

        return $this->twig->load('html/milestones.html.twig')->render([
            'milestones' => $milestones,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($milestones->getActivityIds())->keyByActivityId(),
        ]);
    }
}

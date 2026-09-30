<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Milestone\MilestoneCollector;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
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
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/milestones', name: 'milestones', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(
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
        );

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::MILESTONES,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
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

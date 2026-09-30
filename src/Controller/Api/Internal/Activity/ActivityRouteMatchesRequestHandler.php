<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Activity;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Route\Match\FindRouteMatches\FindRouteMatches;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityRouteMatchesRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private QueryBus $queryBus,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/route-matches', name: 'activity_route_matches', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'])]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.route-matches', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->twig->load('html/activity/_route-matches.html.twig')->render([
                'routeMatches' => $this->queryBus->ask(new FindRouteMatches($activityId))->getRouteMatches(),
            ]),
        );

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }
}

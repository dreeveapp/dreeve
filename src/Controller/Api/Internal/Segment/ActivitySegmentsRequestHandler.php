<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\Segment;

use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivitySegmentsRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private CacheableRenderer $cacheableRenderer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/internal/activities/{activityId}/segments', name: 'activity_segments', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        $activityId = ActivityId::fromString($activityId);

        if (!$this->activityRepository->exists($activityId)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: sprintf('activities.%s.segments', $activityId->toUnprefixedString()),
                cacheTags: CacheTags::of(
                    ActivityCacheTag::for($activityId),
                    RootCacheTag::SEGMENTS,
                ),
            ),
            render: fn (): string => $this->renderFor($activityId),
        ));

        return new HtmlResponse($render->getContent() ?? '', headers: $render->getCacheHeaders());
    }

    private function renderFor(ActivityId $activityId): string
    {
        $activity = $this->activityRepository->find($activityId);

        return $this->twig->load('html/activity/_segments.html.twig')->render([
            'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activityId),
            'sportType' => $activity->getSportType(),
        ]);
    }
}

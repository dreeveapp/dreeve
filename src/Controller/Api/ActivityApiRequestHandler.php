<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\GpxSerializer;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ActivityApiRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private GpxSerializer $gpxSerializer,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/segments', name: 'api_activity_segments', methods: ['GET'], priority: 3)]
    public function segments(string $activityId): HtmlResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromPrefixedOrUnprefixed($activityId));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        return new HtmlResponse($this->twig->load('html/activity/_segments.html.twig')->render([
            'segmentEfforts' => $this->segmentEffortRepository->findByActivityId($activity->getId()),
            'sportType' => $activity->getSportType(),
        ]));
    }

    #[Route(path: '/api/activity/{activityId}/best-efforts', name: 'api_activity_best_efforts', methods: ['GET'], priority: 3)]
    public function bestEfforts(string $activityId): HtmlResponse
    {
        try {
            $activity = $this->activityRepository->find(
                ActivityId::fromPrefixedOrUnprefixed($activityId)
            );
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        return new HtmlResponse($this->twig->load('html/activity/_best-efforts.html.twig')->render([
            'bestEfforts' => $this->activityBestEffortRepository->findByActivity($activity->getId()),
        ]));
    }

    #[Route(path: '/api/activity/{activityId}/route.gpx', name: 'api_activity_gpx', methods: ['GET'], priority: 3)]
    public function gpx(string $activityId): Response
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromPrefixedOrUnprefixed($activityId));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        if (null === $gpx = $this->gpxSerializer->serialize($activity->getId())) {
            throw new NotFoundHttpException(sprintf('Activity "%s" has no GPX data', $activityId));
        }

        $response = new Response($gpx);
        $response->headers->set('Content-Type', 'application/gpx+xml; charset=UTF-8');

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\GpxSerializer;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityGpxRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private GpxSerializer $gpxSerializer,
    ) {
    }

    #[Route(path: '/api/activity/{activityId}/route.gpx', name: 'api_activity_gpx', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
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

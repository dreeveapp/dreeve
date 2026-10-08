<?php

declare(strict_types=1);

namespace App\Controller\Admin\Segment;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Import\ImportMode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityRouteRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/activities/{activityId}/route', name: 'admin_activity_route', requirements: ['activityId' => 'activity-[^/]+'], methods: ['GET'], priority: 10)]
    public function handle(string $activityId): JsonResponse
    {
        if (!$this->importMode->isFiles()) {
            throw new NotFoundHttpException('Page not found');
        }

        $activityId = ActivityId::fromString($activityId);
        if (!$this->activityRepository->exists($activityId)) {
            throw new NotFoundHttpException('Activity not found');
        }

        $streams = $this->activityStreamRepository->findByActivityId($activityId);
        $points = array_values($streams->filterOnType(StreamType::LAT_LNG)?->getData() ?? []);
        $distance = array_values($streams->filterOnType(StreamType::DISTANCE)?->getData() ?? []);
        $altitude = array_values($streams->filterOnType(StreamType::ALTITUDE)?->getData() ?? []);

        if ([] === $points || [] === $distance) {
            throw new NotFoundHttpException('Activity has no route');
        }

        $numberOfPoints = min(count($points), count($distance));

        return new JsonResponse([
            'points' => array_slice($points, 0, $numberOfPoints),
            'distance' => array_slice($distance, 0, $numberOfPoints),
            'altitude' => count($altitude) >= $numberOfPoints ? array_slice($altitude, 0, $numberOfPoints) : [],
        ]);
    }
}

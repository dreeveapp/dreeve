<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Infrastructure\Exception\EntityNotFound;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityStreamsRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}/streams', name: 'api_v1_activity_streams', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): ActivityStreamsResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        return ActivityStreamsResponse::fromStreams(
            $this->activityStreamRepository->findByActivityId($activity->getId())
        );
    }
}

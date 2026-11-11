<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ProvidePagination;
use App\Infrastructure\Repository\Pagination;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivitySegmentEffortsRequestHandler
{
    use ProvidePagination;

    public function __construct(
        private ActivityRepository $activityRepository,
        private SegmentEffortRepository $segmentEffortRepository,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}/segments', name: 'api_v1_activity_segments', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): JsonResponse
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        $segmentEfforts = $this->segmentEffortRepository->findByActivityId($activity->getId())->toArray();
        $total = count($segmentEfforts);
        $pagination = Pagination::fromPageNumberAndSize(
            pageNumber: 1,
            pageSize: max(1, $total),
        );

        return new JsonResponse([
            'segmentEfforts' => array_map(
                static fn (SegmentEffort $segmentEffort): array => [
                    'id' => (string) $segmentEffort->getId(),
                    'segmentId' => (string) $segmentEffort->getSegmentId(),
                    'name' => $segmentEffort->getName(),
                    'startDateLocal' => $segmentEffort->getStartDateTime()->format('Y-m-d\TH:i:s'),
                    'elapsedTimeInSeconds' => $segmentEffort->getElapsedTimeInSeconds(),
                    'distanceInMeter' => $segmentEffort->getDistance()->toMeter()->toInt(),
                    'averagePower' => $segmentEffort->getAverageWatts(),
                    'averageHeartRate' => $segmentEffort->getAverageHeartRate(),
                    'maxHeartRate' => $segmentEffort->getMaxHeartRate(),
                    'rank' => $segmentEffort->getRank(),
                ],
                $segmentEfforts
            ),
            'pagination' => self::buildPagination(
                pagination: $pagination,
                total: $total,
            ),
        ]);
    }
}

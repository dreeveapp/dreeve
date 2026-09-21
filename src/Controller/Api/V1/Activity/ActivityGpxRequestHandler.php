<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\GpxFileName;
use App\Domain\Activity\GpxSerializer;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\AttachmentResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityGpxRequestHandler
{
    private const string CONTENT_TYPE = 'application/gpx+xml; charset=UTF-8';

    public function __construct(
        private ActivityRepository $activityRepository,
        private GpxSerializer $gpxSerializer,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}/gpx', name: 'api_v1_activity_gpx', methods: ['GET'], priority: 3)]
    public function handle(string $activityId): Response
    {
        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        if (null === $gpx = $this->gpxSerializer->serialize($activity->getId())) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_NOT_FOUND,
                error: 'gpx_not_available',
                message: sprintf('Activity "%s" has no GPS or time data to export as GPX.', $activityId),
            );
        }

        return new AttachmentResponse(
            contents: $gpx,
            filename: (string) GpxFileName::for($activity),
            contentType: self::CONTENT_TYPE,
        );
    }
}

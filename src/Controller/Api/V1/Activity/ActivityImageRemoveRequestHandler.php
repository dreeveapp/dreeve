<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Image\ActivityImageId;
use App\Domain\Activity\RemoveActivityImage\RemoveActivityImage;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityImageRemoveRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CommandBus $commandBus,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}/images/{imageId}', name: 'api_v1_activity_image_remove', methods: ['DELETE'], priority: 3)]
    public function handle(string $activityId, string $imageId): Response
    {
        if (!$this->importMode->isFiles()) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: 'Images can only be removed from activities when running in file import mode.',
            );
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        try {
            $activityImageId = ActivityImageId::fromString($imageId);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Image "%s" not found', $imageId));
        }

        try {
            $this->commandBus->dispatch(new RemoveActivityImage(
                activityId: $activity->getId(),
                activityImageId: $activityImageId,
            ));
        } catch (EntityNotFound) {
            throw new NotFoundHttpException(sprintf('Image "%s" not found', $imageId));
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\DeleteActivity\DeleteActivity;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityDeleteRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private CommandBus $commandBus,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}', name: 'api_v1_activity_delete', methods: ['DELETE'], priority: 3)]
    public function handle(string $activityId): Response
    {
        if (!$this->importMode->isFiles()) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: 'Activities can only be deleted when running in file import mode.',
            );
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        $this->commandBus->dispatch(new DeleteActivity($activity->getId()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

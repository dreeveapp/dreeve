<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\UpdateActivity\UpdateActivity;
use App\Domain\Import\ImportMode;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ActivityUpdateRequestHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private CommandBus $commandBus,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/api/v1/activities/{activityId}', name: 'api_v1_activity_update', methods: ['PATCH'], priority: 3)]
    public function handle(string $activityId, Request $request): Response
    {
        if (!$this->importMode->isFiles()) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_CONFLICT,
                error: 'import_mode_not_files',
                message: 'Activities can only be updated when running in file import mode.',
            );
        }

        try {
            $activity = $this->activityRepository->find(ActivityId::fromString($activityId));
        } catch (EntityNotFound|\InvalidArgumentException) {
            throw new NotFoundHttpException(sprintf('Activity "%s" not found', $activityId));
        }

        $gearId = $activity->getGearId();

        try {
            $command = UpdateActivity::fromPayload([
                'activityId' => (string) $activity->getId(),
                'name' => $activity->getOriginalName(),
                'sportType' => $activity->getSportType()->value,
                'description' => $activity->getDescription(),
                'deviceName' => $activity->getDeviceName(),
                'gearId' => null !== $gearId ? (string) $gearId : null,
                'calories' => $activity->getCalories(),
                'isCommute' => $activity->isCommute(),
                'isGroupActivity' => $activity->isGroupActivity(),
                ...ActivityUpdateRequest::fromRequest($request)->getFields(),
            ]);
        } catch (CouldNotDeserializeCommand $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $this->commandBus->dispatch($command);

        return ActivityResponse::detail(
            activity: $this->activityRepository->find($activity->getId()),
            hasGpx: $this->activityStreamRepository->hasOneForActivityAndStreamType($activity->getId(), StreamType::TIME),
        );
    }
}

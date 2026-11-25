<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Settings;

use App\Domain\Athlete\Weight\ImportGarminWeights\ImportGarminWeights;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorCode;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GarminWeightSyncRequestHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
    ) {
    }

    #[Route(path: '/api/v1/athlete/weights/garmin', name: 'api_v1_athlete_weights_garmin_status', methods: ['GET'], priority: 5)]
    public function status(): JsonResponse
    {
        return new JsonResponse(['enabled' => $this->settingsRepository->athleteWeight()->isGarminWeightSyncEnabled()]);
    }

    #[Route(path: '/api/v1/athlete/weights/garmin', name: 'api_v1_athlete_weights_garmin_import', methods: ['POST'], priority: 5)]
    public function import(Request $request): JsonResponse
    {
        if (!$this->settingsRepository->athleteWeight()->isGarminWeightSyncEnabled()) {
            return new ApiErrorResponse(Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST, 'Garmin weight sync is disabled.');
        }
        try {
            $payload = Json::decode($request->getContent());
        } catch (\JsonException) {
            throw new BadRequestHttpException('The request body must be a valid JSON object.');
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new BadRequestHttpException('The request body must be a valid JSON object.');
        }
        try {
            $command = ImportGarminWeights::fromPayload($payload);
        } catch (\InvalidArgumentException|\RuntimeException $error) {
            throw new BadRequestHttpException($error->getMessage());
        }
        $this->commandBus->dispatch($command);

        return new JsonResponse(['imported' => count($command->getWeights())]);
    }
}

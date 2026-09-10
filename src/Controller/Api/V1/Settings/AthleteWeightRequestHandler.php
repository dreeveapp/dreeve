<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Settings;

use App\Domain\Athlete\Weight\DeleteAthleteWeight\DeleteAthleteWeight;
use App\Domain\Athlete\Weight\UpsertAthleteWeight\UpsertAthleteWeight;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class AthleteWeightRequestHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
    ) {
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights', methods: ['GET'], priority: 3)]
    public function list(): JsonResponse
    {
        $weights = $this->settingsRepository->general()
            ->getAthleteWeightHistory($this->settingsRepository->appearance()->getUnitSystem())
            ->findAll();

        return new JsonResponse(['weights' => array_values($weights)]);
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights_update', methods: ['POST'], priority: 3)]
    public function update(Request $request): JsonResponse
    {
        $athleteWeightRequest = AthleteWeightRequest::fromRequest($request);

        $this->commandBus->dispatch(UpsertAthleteWeight::from(
            on: $athleteWeightRequest->getOn(),
            weight: $athleteWeightRequest->getWeight(),
        ));

        return new JsonResponse([
            'on' => $athleteWeightRequest->getOn()->format('Y-m-d'),
            'weight' => $athleteWeightRequest->getWeight(),
        ], Response::HTTP_OK);
    }

    #[Route(path: '/api/v1/athlete/weights/{on}', name: 'api_v1_athlete_weights_delete', methods: ['DELETE'], priority: 3)]
    public function delete(string $on): Response
    {
        try {
            $onDate = SerializableDateTime::createFromFormat(AthleteWeightRequest::DATE_FORMAT, $on);
        } catch (\InvalidArgumentException) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'bad_request',
                message: '"on" must be a date in YYYY-MM-DD format.',
            );
        }

        $this->commandBus->dispatch(DeleteAthleteWeight::from($onDate));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

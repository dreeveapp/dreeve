<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Domain\Athlete\Weight\AthleteWeight;
use App\Domain\Athlete\Weight\DeleteAthleteWeight\DeleteAthleteWeight;
use App\Domain\Athlete\Weight\UpsertAthleteWeight\UpsertAthleteWeight;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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

        return new JsonResponse([
            'weights' => array_values(array_map(
                static fn (AthleteWeight $weight): array => [
                    'on' => $weight->getOn()->format('Y-m-d'),
                    'weight' => $weight->getWeight()->toFloat(),
                ],
                $weights,
            )),
        ]);
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights_record', methods: ['POST'], priority: 3)]
    public function record(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        AthleteWeightRequest $request,
    ): JsonResponse {
        $on = $request->on;

        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $general['athlete'] ?? [];
        /** @var list<mixed> $weightHistory */
        $weightHistory = is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : [];

        $exists = false;
        foreach ($weightHistory as $entry) {
            if (is_array($entry) && $on === ($entry['on'] ?? null)) {
                $exists = true;
            }
        }

        $this->commandBus->dispatch(UpsertAthleteWeight::from(
            on: SerializableDateTime::fromString($on),
            weight: $request->weight,
        ));

        return new JsonResponse([
            'status' => $exists ? 'updated' : 'created',
            'on' => $on,
            'weight' => $request->weight,
        ], $exists ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    #[Route(path: '/api/v1/athlete/weights/{on}', name: 'api_v1_athlete_weights_delete', methods: ['DELETE'], priority: 3)]
    public function delete(string $on): Response
    {
        if (!SerializableDateTime::isValidDateString($on)) {
            return new ApiErrorResponse(
                statusCode: Response::HTTP_BAD_REQUEST,
                error: 'bad_request',
                message: '"on" must be a date in YYYY-MM-DD format.',
            );
        }

        $this->commandBus->dispatch(DeleteAthleteWeight::from(SerializableDateTime::fromString($on)));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

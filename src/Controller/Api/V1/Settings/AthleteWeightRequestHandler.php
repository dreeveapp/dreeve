<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Settings;

use App\Domain\Athlete\Weight\DeleteAthleteWeight\DeleteAthleteWeight;
use App\Domain\Athlete\Weight\UpdateAthleteWeight\UpdateAthleteWeight;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\Api\ApiErrorCode;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use App\Infrastructure\Http\Api\ProvidePagination;
use App\Infrastructure\Repository\Pagination;
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
    use ProvidePagination;

    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private CommandBus $commandBus,
    ) {
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights', methods: ['GET'], priority: 3)]
    public function list(): JsonResponse
    {
        $weights = $this->settingsRepository->athleteWeight()
            ->getHistory($this->settingsRepository->appearance()->getUnitSystem())
            ->findAll();

        $total = count($weights);
        $pagination = Pagination::fromPageNumberAndSize(1, max(1, $total));

        return new JsonResponse([
            'weights' => array_values($weights),
            'pagination' => self::buildPagination($pagination, $total),
        ]);
    }

    #[Route(path: '/api/v1/athlete/weights', name: 'api_v1_athlete_weights_update', methods: ['POST'], priority: 3)]
    public function update(Request $request): JsonResponse
    {
        $athleteWeightRequest = AthleteWeightRequest::fromRequest($request);

        $this->commandBus->dispatch(UpdateAthleteWeight::from(
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
                error: ApiErrorCode::BAD_REQUEST,
                message: '"on" must be a date in YYYY-MM-DD format.',
            );
        }

        $this->commandBus->dispatch(DeleteAthleteWeight::from($onDate));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}

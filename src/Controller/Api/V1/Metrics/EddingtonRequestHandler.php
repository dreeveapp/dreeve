<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Metrics;

use App\Domain\Activity\Eddington\Eddington;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class EddingtonRequestHandler
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
    ) {
    }

    #[Route(path: '/api/v1/metrics/eddington', name: 'api_v1_metrics_eddington', methods: ['GET'], priority: 3)]
    public function handle(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (Eddington $eddington): array => [
                'label' => $eddington->getLabel(),
                'sportTypes' => $eddington->getConfig()->getSportTypesToInclude()->map(
                    static fn (SportType $sportType): string => $sportType->value
                ),
                'unit' => $eddington->getUnitSystem()->distanceSymbol(),
                'number' => $eddington->getNumber(),
                'daysNeededForNextNumber' => $eddington->getDaysToCompleteForFutureNumbers()[$eddington->getNumber() + 1] ?? $eddington->getNumber() + 1,
                'longestDistanceInADay' => $eddington->getLongestDistanceInADay(),
            ],
            $this->eddingtonCalculator->calculate(...UnitSystem::cases()),
        ));
    }
}

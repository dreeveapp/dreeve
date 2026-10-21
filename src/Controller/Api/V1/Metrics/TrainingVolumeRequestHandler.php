<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Metrics;

use App\Domain\Dashboard\Widget\TrainingGoals\FindTrainingGoalMetrics\FindTrainingGoalMetrics;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class TrainingVolumeRequestHandler
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    #[Route(path: '/api/v1/metrics/training-volume', name: 'api_v1_metrics_training_volume', methods: ['GET'], priority: 3)]
    public function handle(Request $request): JsonResponse
    {
        $filters = MetricsFilters::fromRequest($request);
        $from = $filters->getFrom();
        $to = $filters->getTo();

        $trainingVolume = $this->queryBus->ask(new FindTrainingGoalMetrics(
            sportTypes: $filters->getSportTypes(),
            from: $from,
            to: $to,
        ));

        return new JsonResponse([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'numberOfActivities' => $trainingVolume->getNumberOfActivities()->toInt(),
            'movingTimeInSeconds' => $trainingVolume->getMovingTime()->toInt(),
            'distanceInMeter' => $trainingVolume->getDistance()->toMeter()->toInt(),
            'elevationInMeter' => $trainingVolume->getElevation()->toInt(),
            'calories' => $trainingVolume->getCalories()->toInt(),
        ]);
    }
}

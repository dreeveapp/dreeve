<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Metrics;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Dashboard\Widget\TrainingLoad\AcRatio;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingLoadChart;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingMetrics;
use App\Domain\Dashboard\Widget\TrainingLoad\TSB;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class TrainingLoadRequestHandler
{
    public function __construct(
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private Clock $clock,
    ) {
    }

    #[Route(path: '/api/v1/metrics/training-load', name: 'api_v1_metrics_training_load', methods: ['GET'], priority: 3)]
    public function handle(): JsonResponse
    {
        $now = $this->clock->getCurrentDateTimeImmutable();

        $trainingMetrics = TrainingMetrics::create($this->dailyTrainingLoad->calculateForDateRange(DateRange::fromDates(
            from: $now->modify('- '.(TrainingLoadChart::NUMBER_OF_DAYS_TO_DISPLAY + TrainingLoadChart::EMA_WARMUP_DAYS).' days'),
            till: $now,
        )));

        $tsb = $trainingMetrics->getCurrentTsb();
        $acRatio = $trainingMetrics->getCurrentAcRatio();

        return new JsonResponse([
            'ctl' => $trainingMetrics->getCurrentCtl(),
            'atl' => $trainingMetrics->getCurrentAtl(),
            'tsb' => $tsb instanceof TSB ? [
                'value' => $tsb->getValue(),
                'status' => $tsb->getStatus()->name,
            ] : null,
            'acRatio' => $acRatio instanceof AcRatio ? [
                'value' => $acRatio->getValue(),
                'status' => $acRatio->getStatus()->name,
            ] : null,
            'weeklyTrimp' => $trainingMetrics->getWeeklyTrimp(),
            'monotony' => $trainingMetrics->getCurrentMonotony(),
            'strain' => $trainingMetrics->getCurrentStrain(),
            'restDaysLast7Days' => $this->queryBus->ask(new FindNumberOfRestDays(DateRange::fromDates(
                from: $now->modify('-6 days'),
                till: $now,
            )))->getNumberOfRestDays(),
        ]);
    }
}

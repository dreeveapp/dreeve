<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Dashboard\Widget\TrainingLoad\AcRatio;
use App\Domain\Dashboard\Widget\TrainingLoad\FindNumberOfRestDays\FindNumberOfRestDays;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingLoadChart;
use App\Domain\Dashboard\Widget\TrainingLoad\TrainingMetrics;
use App\Domain\Dashboard\Widget\TrainingLoad\TSB;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;

final readonly class GetTrainingLoad implements AITool
{
    public function __construct(
        private DailyTrainingLoad $dailyTrainingLoad,
        private QueryBus $queryBus,
        private Clock $clock,
    ) {
    }

    public function getName(): string
    {
        return 'get_training_load';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s current training load: fitness (CTL), fatigue (ATL), form (TSB), the acute:chronic workload ratio, weekly TRIMP, monotony, strain and the number of rest days in the last 7 days.
            Use this tool when the user asks how fit, tired or fresh they are, whether they are overtraining, or if they are ready for a hard effort.
            DESC;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $now = $this->clock->getCurrentDateTimeImmutable();

        $trainingMetrics = TrainingMetrics::create($this->dailyTrainingLoad->calculateForDateRange(DateRange::fromDates(
            from: $now->modify('- '.(TrainingLoadChart::NUMBER_OF_DAYS_TO_DISPLAY + TrainingLoadChart::EMA_WARMUP_DAYS).' days'),
            till: $now,
        )));

        $tsb = $trainingMetrics->getCurrentTsb();
        $acRatio = $trainingMetrics->getCurrentAcRatio();

        return [
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
        ];
    }
}

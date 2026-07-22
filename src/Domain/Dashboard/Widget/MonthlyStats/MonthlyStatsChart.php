<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\MonthlyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStatsResponse;
use App\Domain\Calendar\Month;
use App\Domain\Dashboard\StatsContext;
use App\Infrastructure\Theme\Theme;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MonthlyStatsChart
{
    private function __construct(
        private ActivityType $activityType,
        private FindMonthlyStatsResponse $monthlyStats,
        private StatsContext $context,
        private UnitSystem $unitSystem,
        private int $enableLastXYearsByDefault,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        ActivityType $activityType,
        FindMonthlyStatsResponse $monthlyStats,
        StatsContext $context,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
        ?int $enableLastXYearsByDefault = null,
    ): self {
        return new self(
            activityType: $activityType,
            monthlyStats: $monthlyStats,
            context: $context,
            unitSystem: $unitSystem,
            enableLastXYearsByDefault: $enableLastXYearsByDefault ?? 20,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $series = [];

        $firstMonth = $this->monthlyStats->getFirstMonthFor($this->activityType);
        $lastMonth = $this->monthlyStats->getLastMonthFor($this->activityType);
        $years = Years::create(
            startDate: $firstMonth->getFirstDay(),
            endDate: $lastMonth->getFirstDay(),
        )->reverse();

        $selectedSeries = [];
        $delta = 1;
        /** @var Year $year */
        foreach ($years as $year) {
            $selectedSeries[$year->toInt()] = $delta <= $this->enableLastXYearsByDefault;
            ++$delta;

            $data = [];
            /** @var Month $month */
            foreach ($year->getMonths() as $month) {
                $stats = $this->monthlyStats->getForMonthAndActivityType(
                    month: $month,
                    activityType: $this->activityType
                );

                if (is_null($stats)) {
                    $data[] = $month->isBefore($firstMonth) || $month->isAfter($lastMonth) ? null : 0;
                } else {
                    $data[] = match ($this->context) {
                        StatsContext::MOVING_TIME => $stats['movingTime']->toHour()->toInt(),
                        StatsContext::DISTANCE => $stats['distance']->toUnitSystem($this->unitSystem)->toFloat(),
                        StatsContext::ELEVATION => $stats['elevation']->toUnitSystem($this->unitSystem)->toFloat(),
                    };
                }
            }

            $series[] = [
                'name' => (string) $year->toInt(),
                'type' => 'line',
                'smooth' => true,
                'itemStyle' => [
                    'color' => Theme::getColorForYear($year),
                ],
                'data' => $data,
            ];
        }

        return [
            'backgroundColor' => null,
            'animation' => false,
            'grid' => [
                'top' => '50px',
                'left' => '2px',
                'right' => '10px',
                'bottom' => '2%',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'valueFormatter' => match ($this->context) {
                    StatsContext::MOVING_TIME => 'callback:formatHours',
                    StatsContext::DISTANCE => 'callback:formatDistance',
                    StatsContext::ELEVATION => 'callback:formatElevation',
                },
            ],
            'legend' => [
                'selected' => $selectedSeries,
                'data' => array_map(
                    fn (Year $year): string => (string) $year->toInt(),
                    $years->toArray()
                ),
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => [
                    $this->translator->trans('Jan'),
                    $this->translator->trans('Feb'),
                    $this->translator->trans('Mar'),
                    $this->translator->trans('Apr'),
                    $this->translator->trans('May'),
                    $this->translator->trans('Jun'),
                    $this->translator->trans('Jul'),
                    $this->translator->trans('Aug'),
                    $this->translator->trans('Sep'),
                    $this->translator->trans('Oct'),
                    $this->translator->trans('Nov'),
                    $this->translator->trans('Dec'),
                ],
                'axisPointer' => [
                    'type' => 'shadow',
                ],
            ],
            'yAxis' => [
                'type' => 'value',
                'axisLabel' => [
                    'formatter' => match ($this->context) {
                        StatsContext::MOVING_TIME => '{value}h',
                        StatsContext::DISTANCE => '{value}'.$this->unitSystem->distanceSymbol(),
                        StatsContext::ELEVATION => '{value}'.$this->unitSystem->elevationSymbol(),
                    },
                ],
            ],
            'series' => $series,
        ];
    }
}

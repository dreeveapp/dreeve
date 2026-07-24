<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Infrastructure\Measurement\Length\ConvertableToMeter;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Theme\Theme;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BestEffortChart
{
    private function __construct(
        private ActivityType $activityType,
        private BestEffortPeriod $period,
        private BestEffortsCalculator $bestEffortsCalculator,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        ActivityType $activityType,
        BestEffortPeriod $period,
        BestEffortsCalculator $bestEffortsCalculator,
        TranslatorInterface $translator,
    ): self {
        return new self(
            activityType: $activityType,
            period: $period,
            bestEffortsCalculator: $bestEffortsCalculator,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $series = [];

        $sportTypes = $this->bestEffortsCalculator->getSportTypesFor(
            period: $this->period,
            activityType: $this->activityType
        );

        foreach ($sportTypes as $sportType) {
            $series[] = [
                'name' => $sportType->trans($this->translator),
                'type' => 'bar',
                'cursor' => 'default',
                'barGap' => 0,
                'emphasis' => [
                    'focus' => 'none',
                ],
                'label' => [
                    'show' => false,
                ],
                'itemStyle' => [
                    'color' => Theme::getColorForSportType($sportType),
                ],
                'data' => array_filter(array_map(
                    fn (ConvertableToMeter $distance): ?int => $this->bestEffortsCalculator->for(
                        period: $this->period,
                        sportType: $sportType,
                        distance: $distance,
                    )?->getTimeInSeconds(),
                    $this->activityType->getDistancesForBestEffortCalculation()
                )),
            ];
        }

        return [
            'animation' => true,
            'color' => ['#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'],
            'grid' => [
                'top' => '30px',
                'left' => '2px',
                'right' => '0',
                'bottom' => '2%',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'none',
                ],
                'valueFormatter' => 'callback:formatSeconds',
            ],
            'legend' => [
                'show' => true,
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'axisTick' => [
                        'show' => false,
                    ],
                    'data' => array_map(
                        fn (Unit $distance): string => sprintf('%s%s', $distance->isLowerThanOne() ? round($distance->toFloat(), 1) : $distance->toInt(), $distance->getSymbol()),
                        $this->activityType->getDistancesForBestEffortCalculation()
                    ),
                ],
            ],
            'yAxis' => [
                'type' => 'log',
                'axisLabel' => [
                    'formatter' => 'callback:formatSeconds',
                    'showMaxLabel' => false,
                ],
            ],
            'series' => $series,
        ];
    }
}

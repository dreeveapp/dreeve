<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityType;
use App\Domain\Theme\Theme;
use App\Infrastructure\ECharts\EChartsCallback;
use App\Infrastructure\Measurement\Unit;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BestEffortChart
{
    use ProvideTimeFormats;

    private function __construct(
        private ActivityType $activityType,
        private BestEffortPeriod $period,
        private BestEfforts $bestEfforts,
        private TranslatorInterface $translator,
        private Theme $theme,
        private UnitSystem $unitSystem,
    ) {
    }

    public static function create(
        ActivityType $activityType,
        BestEffortPeriod $period,
        BestEfforts $bestEfforts,
        TranslatorInterface $translator,
        Theme $theme,
        UnitSystem $unitSystem,
    ): self {
        return new self(
            activityType: $activityType,
            period: $period,
            bestEfforts: $bestEfforts,
            translator: $translator,
            theme: $theme,
            unitSystem: $unitSystem,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $series = [];
        $yAxisName = null;
        $showsPace = false;

        $sportTypes = $this->bestEfforts->getSportTypesFor(
            period: $this->period,
            activityType: $this->activityType
        );
        $distances = $this->activityType->getDistancesForBestEffortCalculation();

        foreach ($sportTypes as $sportType) {
            $sportTypeName = $sportType->trans($this->translator);
            $showsPace = $sportType->getVelocityDisplayPreference() instanceof SecPerKm;
            $velocityUnit = $showsPace ? $this->unitSystem->paceSymbol() : $sportType->speedSymbol($this->unitSystem);
            $yAxisName = $velocityUnit;

            $data = [];
            foreach ($distances as $distance) {
                $bestEffort = $this->bestEfforts->for(
                    period: $this->period,
                    sportType: $sportType,
                    distance: $distance,
                );
                if (!$bestEffort instanceof ActivityBestEffort) {
                    $data[] = null;
                    continue;
                }

                $velocity = MetersPerSecond::from($distance->toMeter()->toFloat() / $bestEffort->getTimeInSeconds());
                if ($showsPace) {
                    $value = (int) round($velocity->toSecPerKm()->toUnitSystem($this->unitSystem)->toFloat());
                    $formattedValue = $this->formatDurationAsPaddedClock($value);
                } else {
                    $value = round($sportType->toDisplaySpeed($velocity->toKmPerHour())->toUnitSystem($this->unitSystem)->toFloat(), 1);
                    $formattedValue = (string) $value;
                }

                $data[] = [
                    'value' => $value,
                    'tooltip' => sprintf(
                        '%s: <strong>%s</strong> · %s %s',
                        htmlspecialchars($sportTypeName),
                        $bestEffort->getFormattedTime(),
                        $formattedValue,
                        htmlspecialchars($velocityUnit),
                    ),
                ];
            }

            $series[] = [
                'name' => $sportTypeName,
                'type' => 'line',
                'symbolSize' => 8,
                'emphasis' => [
                    'focus' => 'none',
                ],
                'itemStyle' => [
                    'color' => $this->theme->getColorForSportType($sportType),
                ],
                'data' => $data,
            ];
        }

        $yAxis = [
            'type' => 'value',
            'scale' => true,
            'name' => $yAxisName,
        ];
        if ($showsPace) {
            $yAxis['inverse'] = true;
            $yAxis['axisLabel'] = [
                'formatter' => EChartsCallback::formatSecondsTrimZero(),
            ];
        }

        return [
            'animation' => true,
            'color' => ['#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4', '#ea7ccc'],
            'grid' => [
                'top' => '40px',
                'left' => '2px',
                'right' => '10px',
                'bottom' => '2%',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
                ],
                'formatter' => EChartsCallback::formatDataTooltip(),
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
                        fn (Unit $distance): string => $this->formatDistance($distance),
                        $distances
                    ),
                ],
            ],
            'yAxis' => $yAxis,
            'series' => $series,
        ];
    }

    private function formatDistance(Unit $distance): string
    {
        return sprintf('%s%s', $distance->isLowerThanOne() ? round($distance->toFloat(), 1) : $distance->toInt(), $distance->getSymbol());
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\Month;
use App\Domain\Theme\Theme;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PerMonthChart
{
    private function __construct(
        /** @var array<int, array{0: int, 1: SportType, 2: int|float}> */
        private array $valuesPerMonth,
        private ?string $yAxisName,
        private int $yAxisNameGap,
        private string $gridLeft,
        private TranslatorInterface $translator,
        private Theme $theme,
    ) {
    }

    /**
     * @param array<int, array{0: int, 1: SportType, 2: int|float}> $valuesPerMonth
     */
    public static function create(
        array $valuesPerMonth,
        ?string $yAxisName,
        int $yAxisNameGap,
        string $gridLeft,
        TranslatorInterface $translator,
        Theme $theme,
    ): self {
        return new self(
            valuesPerMonth: $valuesPerMonth,
            yAxisName: $yAxisName,
            yAxisNameGap: $yAxisNameGap,
            gridLeft: $gridLeft,
            translator: $translator,
            theme: $theme,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $series = [];
        $monthlyValues = [];
        $monthlyTotals = array_fill(1, 12, 0);
        $sportTypes = [];

        foreach ($this->valuesPerMonth as [$monthNumber, $sportType, $value]) {
            if ($value <= 0) {
                continue;
            }
            $roundedValue = round($value);

            $monthlyValues[$sportType->value][$monthNumber] = $roundedValue;
            $monthlyTotals[$monthNumber] += $roundedValue;

            $sportTypes[$sportType->value] = $sportType;
        }

        foreach ($sportTypes as $key => $sportType) {
            $data = [];

            for ($month = 1; $month <= 12; ++$month) {
                $data[] = [
                    'name' => $monthlyTotals[$month] ?? 0,
                    'value' => $monthlyValues[$key][$month] ?? 0,
                    'itemStyle' => [
                        'color' => $this->theme->getColorForSportType($sportType),
                    ],
                ];
            }

            $series[] = [
                'name' => $sportType->trans($this->translator),
                'type' => 'bar',
                'stack' => 'total',
                'label' => [
                    'show' => false,
                    'position' => 'top',
                    'formatter' => '{b}',
                ],
                'data' => $data,
            ];
        }

        if ([] !== $series) {
            $lastKey = array_key_last($series);
            $series[$lastKey]['label']['show'] = true;
        }

        $xAxisLabels = [];
        for ($monthNumber = 1; $monthNumber <= 12; ++$monthNumber) {
            $date = sprintf('%s-%02d-01', 2025, $monthNumber);
            $month = Month::fromDate(SerializableDateTime::fromString($date));
            $xAxisLabels[] = $month->getShortLabelWithoutYear();
        }

        $yAxis = [
            'type' => 'value',
            'min' => 0,
        ];
        if (null !== $this->yAxisName) {
            $yAxis = [
                ...$yAxis,
                'name' => $this->yAxisName,
                'nameRotate' => 90,
                'nameLocation' => 'middle',
                'nameGap' => $this->yAxisNameGap,
            ];
        }

        return [
            'animation' => false,
            'backgroundColor' => null,
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
                ],
            ],
            'grid' => [
                'left' => $this->gridLeft,
                'right' => '0%',
                'bottom' => '0%',
                'top' => '15px',
                'containLabel' => true,
            ],
            'yAxis' => [$yAxis],
            'xAxis' => [
                [
                    'type' => 'category',
                    'data' => $xAxisLabels,
                    'axisTick' => [
                        'show' => false,
                    ],
                ],
            ],
            'series' => $series,
        ];
    }
}

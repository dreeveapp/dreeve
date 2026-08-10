<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Calendar\Month;
use App\Domain\Theme\Theme;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityCountPerMonthChart
{
    private function __construct(
        /** @var array<int, array{0: int, 1: SportType, 2: int}> */
        private array $activityCountPerMonth,
        private TranslatorInterface $translator,
        private Theme $theme,
    ) {
    }

    /**
     * @param array<int, array{0: int, 1: SportType, 2: int}> $activityCountPerMonth
     */
    public static function create(
        array $activityCountPerMonth,
        TranslatorInterface $translator,
        Theme $theme,
    ): self {
        return new self(
            activityCountPerMonth: $activityCountPerMonth,
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
        $monthlyActivityCounts = [];
        $monthlyTotals = array_fill(1, 12, 0);
        $sportTypes = [];

        foreach ($this->activityCountPerMonth as [$monthNumber, $sportType, $count]) {
            if (0 === $count) {
                continue;
            }
            $monthlyActivityCounts[$sportType->value][$monthNumber] = $count;
            $monthlyTotals[$monthNumber] += $count;

            $sportTypes[$sportType->value] = $sportType;
        }

        foreach ($sportTypes as $key => $sportType) {
            $data = [];

            for ($month = 1; $month <= 12; ++$month) {
                $data[] = [
                    'name' => $monthlyTotals[$month] ?? 0,
                    'value' => $monthlyActivityCounts[$key][$month] ?? 0,
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

        // Enable label only on top series.
        if ([] !== $series) {
            $lastKey = array_key_last($series);
            $series[$lastKey]['label']['show'] = true;
        }

        // X axis labels.
        $xAxisLabels = [];
        for ($monthNumber = 1; $monthNumber <= 12; ++$monthNumber) {
            // We don't care about the year, we only need this to display the month label.
            $date = sprintf('%s-%02d-01', 2025, $monthNumber);
            $month = Month::fromDate(SerializableDateTime::fromString($date));
            $xAxisLabels[] = $month->getShortLabelWithoutYear();
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
                'left' => '2px',
                'right' => '0%',
                'bottom' => '0%',
                'top' => '15px',
                'containLabel' => true,
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'min' => 0,
                ],
            ],
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

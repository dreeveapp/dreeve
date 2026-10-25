<?php

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DaytimeStatsChart
{
    private function __construct(
        private DaytimeStats $daytimeStats,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        DaytimeStats $daytimeStats,
        TranslatorInterface $translator,
    ): self {
        return new self(
            $daytimeStats,
            $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $numberOfWorkoutsPerHour = $this->daytimeStats->getNumberOfWorkoutsPerHour();
        $totalNumberOfWorkouts = array_sum(array_column($numberOfWorkoutsPerHour, 'numberOfWorkouts'));

        $series = [];
        $legend = [];
        foreach (Daytime::cases() as $daytime) {
            $data = [];
            $daytimeNumberOfWorkouts = 0;
            foreach ($numberOfWorkoutsPerHour as $hour => $statistic) {
                if ($statistic['daytime'] !== $daytime) {
                    $data[] = '-';
                    continue;
                }
                $data[] = [
                    'value' => $statistic['numberOfWorkouts'],
                    'name' => sprintf('%dh - %dh', $hour, ($hour + 1) % 24),
                ];
                $daytimeNumberOfWorkouts += $statistic['numberOfWorkouts'];
            }

            $name = sprintf(
                '%s (%s%%)',
                $daytime->trans($this->translator),
                $totalNumberOfWorkouts > 0 ? round($daytimeNumberOfWorkouts / $totalNumberOfWorkouts * 100, 1) : 0,
            );
            $legend[] = $name;

            $series[] = [
                'name' => $name,
                'type' => 'bar',
                'stack' => 'daytime',
                'barCategoryGap' => '18%',
                'emphasis' => [
                    'focus' => 'none',
                ],
                'itemStyle' => [
                    'color' => $daytime->getColor(),
                    'borderRadius' => [2, 2, 0, 0],
                ],
                'data' => $data,
            ];
        }

        return [
            'backgroundColor' => null,
            'animationDuration' => 300,
            'grid' => [
                'left' => 40,
                'right' => 16,
                'top' => 40,
                'bottom' => 30,
            ],
            'legend' => [
                'top' => 0,
                'left' => 'center',
                'icon' => 'rect',
                'itemWidth' => 10,
                'itemHeight' => 10,
                'data' => $legend,
            ],
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => '{b}: <strong>{c}</strong>',
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => array_map(static fn (int $hour): string => $hour.'h', array_keys($numberOfWorkoutsPerHour)),
                'axisTick' => [
                    'show' => false,
                ],
                'axisLabel' => [
                    'interval' => 2,
                    'fontFamily' => 'monospace',
                    'fontSize' => 11,
                ],
            ],
            'yAxis' => [
                'type' => 'value',
                'minInterval' => 1,
                'axisLabel' => [
                    'fontFamily' => 'monospace',
                    'fontSize' => 11,
                ],
            ],
            'series' => $series,
        ];
    }
}

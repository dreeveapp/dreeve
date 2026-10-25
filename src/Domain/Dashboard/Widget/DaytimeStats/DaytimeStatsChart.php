<?php

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DaytimeStatsChart
{
    /**
     * @param array<int, int> $numberOfActivitiesPerHour
     */
    private function __construct(
        private array $numberOfActivitiesPerHour,
        private DaytimeStartHours $startHours,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<int, int> $numberOfActivitiesPerHour
     */
    public static function create(
        array $numberOfActivitiesPerHour,
        DaytimeStartHours $startHours,
        TranslatorInterface $translator,
    ): self {
        return new self(
            $numberOfActivitiesPerHour,
            $startHours,
            $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $totalNumberOfActivities = array_sum($this->numberOfActivitiesPerHour);

        $series = [];
        $legend = [];
        foreach (Daytime::cases() as $daytime) {
            $data = [];
            $daytimeNumberOfActivities = 0;
            foreach ($this->numberOfActivitiesPerHour as $hour => $numberOfActivities) {
                if ($this->startHours->findDaytime($hour) !== $daytime) {
                    $data[] = '-';
                    continue;
                }
                $data[] = [
                    'value' => $numberOfActivities,
                    'name' => sprintf('%dh - %dh', $hour, ($hour + 1) % 24),
                ];
                $daytimeNumberOfActivities += $numberOfActivities;
            }

            $name = sprintf(
                '%s (%s%%)',
                $daytime->trans($this->translator),
                $totalNumberOfActivities > 0 ? round($daytimeNumberOfActivities / $totalNumberOfActivities * 100, 1) : 0,
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
                'data' => array_map(static fn (int $hour): string => $hour.'h', array_keys($this->numberOfActivitiesPerHour)),
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

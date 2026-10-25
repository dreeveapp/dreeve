<?php

namespace App\Domain\Dashboard\Widget\WeekdayStats;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WeekdayStatsChart
{
    /**
     * @param array<int, array{weekday: Weekday, numberOfActivities: int, distance: Kilometer, averageDistance: Kilometer, elevation: Meter, movingTime: Seconds, percentage: float|int}> $statsPerWeekday
     */
    private function __construct(
        private array $statsPerWeekday,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<int, array{weekday: Weekday, numberOfActivities: int, distance: Kilometer, averageDistance: Kilometer, elevation: Meter, movingTime: Seconds, percentage: float|int}> $statsPerWeekday
     */
    public static function create(
        array $statsPerWeekday,
        TranslatorInterface $translator,
    ): self {
        return new self($statsPerWeekday, $translator);
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        return [
            'backgroundColor' => null,
            'animation' => true,
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'containLabel' => true,
            ],
            'legend' => [
                'show' => false,
            ],
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => '{d}%',
            ],
            'series' => [
                [
                    'type' => 'pie',
                    'itemStyle' => [
                        'borderColor' => '#fff',
                        'borderWidth' => 2,
                    ],
                    'label' => [
                        'formatter' => "{weekday|{b}}\n{sub|{d}%}",
                        'lineHeight' => 15,
                        'rich' => [
                            'weekday' => [
                                'fontWeight' => 'bold',
                            ],
                            'sub' => [
                                'fontSize' => 12,
                            ],
                        ],
                    ],
                    'data' => $this->getData(),
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getData(): array
    {
        $data = [];
        foreach ($this->statsPerWeekday as $statistic) {
            $data[] = [
                'value' => $statistic['percentage'],
                'name' => $statistic['weekday']->trans($this->translator),
            ];
        }

        return $data;
    }
}

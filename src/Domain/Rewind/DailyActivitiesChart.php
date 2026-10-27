<?php

namespace App\Domain\Rewind;

use App\Infrastructure\ECharts\EChartsCallback;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DailyActivitiesChart
{
    private SerializableDateTime $fromDate;
    private SerializableDateTime $toDate;

    private function __construct(
        /** @var array<string, int> */
        private array $movingTimePerDay,
        private Year $year,
        private TranslatorInterface $translator,
    ) {
        $this->fromDate = SerializableDateTime::fromString(sprintf('%s-01-01 00:00:00', $this->year));
        $this->toDate = SerializableDateTime::fromString(sprintf('%s-12-31 23:59:59', $this->year));
    }

    /**
     * @param array<string, int> $movingTimePerDay
     */
    public static function create(
        array $movingTimePerDay,
        Year $year,
        TranslatorInterface $translator,
    ): self {
        return new self(
            movingTimePerDay: $movingTimePerDay,
            year: $year,
            translator: $translator,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = [];

        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod(
            $this->fromDate,
            $interval,
            $this->toDate,
        );

        foreach ($period as $dt) {
            $day = $dt->format('Y-m-d');
            if (!array_key_exists($day, $this->movingTimePerDay)) {
                $data[] = [$day, 0];

                continue;
            }

            $level = max(min(floor($this->movingTimePerDay[$day] / 1000), 4), 1);
            $data[] = [$day, $level];
        }

        return [
            'backgroundColor' => null,
            'animation' => false,
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => EChartsCallback::formatActivityGridTooltip(),
            ],
            'visualMap' => [
                'type' => 'piecewise',
                'selectedMode' => false,
                'left' => 'center',
                'bottom' => 0,
                'orient' => 'horizontal',
                'pieces' => [
                    [
                        'value' => 0,
                        'color' => '#cdd9e5',
                        'label' => ' ',
                    ],
                    [
                        'value' => 1,
                        'color' => '#233A25',
                        'label' => ' ',
                    ],
                    [
                        'value' => 2,
                        'color' => '#345F2F',
                        'label' => ' ',
                    ],
                    [
                        'value' => 3,
                        'color' => '#58983D',
                        'label' => ' ',
                    ],
                    [
                        'value' => 4,
                        'color' => '#75C84F',
                        'label' => $this->translator->trans('more'),
                    ],
                ],
            ],
            'calendar' => [
                'left' => 40,
                'cellSize' => [
                    'auto',
                    13,
                ],
                'range' => [$this->fromDate->format('Y-m-d'), $this->toDate->format('Y-m-d')],
                'itemStyle' => [
                    'borderWidth' => 3,
                    'opacity' => 0,
                ],
                'splitLine' => [
                    'show' => false,
                ],
                'yearLabel' => [
                    'show' => false,
                ],
                'dayLabel' => [
                    'firstDay' => 1,
                    'align' => 'right',
                    'fontSize' => 10,
                    'nameMap' => [
                        $this->translator->trans('Sun'),
                        $this->translator->trans('Mon'),
                        $this->translator->trans('Tue'),
                        $this->translator->trans('Wed'),
                        $this->translator->trans('Thu'),
                        $this->translator->trans('Fri'),
                        $this->translator->trans('Sat'),
                    ],
                ],
            ],
            'series' => [
                'type' => 'heatmap',
                'coordinateSystem' => 'calendar',
                'data' => $data,
            ],
        ];
    }
}

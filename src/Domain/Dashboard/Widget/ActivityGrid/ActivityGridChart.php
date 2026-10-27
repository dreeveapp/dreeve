<?php

namespace App\Domain\Dashboard\Widget\ActivityGrid;

use App\Infrastructure\ECharts\EChartsCallback;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityGridChart
{
    private function __construct(
        private ActivityGrid $activityGrid,
        private SerializableDateTime $fromDate,
        private SerializableDateTime $toDate,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        ActivityGrid $activityGrid,
        SerializableDateTime $fromDate,
        SerializableDateTime $toDate,
        TranslatorInterface $translator,
    ): self {
        return new self(
            activityGrid: $activityGrid,
            fromDate: $fromDate,
            toDate: $toDate,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'backgroundColor' => null,
            'animation' => true,
            'legend' => [
                'show' => false,
            ],
            'title' => [
                'left' => 'center',
                'text' => sprintf('%s - %s', $this->fromDate->translatedFormat('M Y'), $this->toDate->translatedFormat('M Y')),
                'textStyle' => [
                    'fontSize' => 14,
                ],
            ],
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
                'pieces' => $this->activityGrid->getPieces(),
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
                'data' => $this->activityGrid->getData(),
                'name' => $this->activityGrid->getGridType()->value,
            ],
        ];
    }
}

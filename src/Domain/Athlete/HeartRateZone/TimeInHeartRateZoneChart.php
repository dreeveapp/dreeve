<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TimeInHeartRateZoneChart
{
    private function __construct(
        private TimeInHeartRateZones $timeInHeartRateZones,
        private TranslatorInterface $translator,
    ) {
    }

    public static function create(
        TimeInHeartRateZones $timeInHeartRateZones,
        TranslatorInterface $translator,
    ): self {
        return new self(
            timeInHeartRateZones: $timeInHeartRateZones,
            translator: $translator,
        );
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
                        'formatter' => "{zone|{b}}\n{sub|{d}%}",
                        'lineHeight' => 15,
                        'rich' => [
                            'zone' => [
                                'fontWeight' => 'bold',
                            ],
                            'sub' => [
                                'fontSize' => 12,
                            ],
                        ],
                    ],
                    'data' => [
                        [
                            'value' => $this->timeInHeartRateZones->getTimeInZoneOne(),
                            'name' => $this->translator->trans('Zone 1 (recovery)'),
                            'itemStyle' => [
                                'color' => HeartRateZone::COLORS[HeartRateZone::ONE],
                            ],
                        ],
                        [
                            'value' => $this->timeInHeartRateZones->getTimeInZoneTwo(),
                            'name' => $this->translator->trans('Zone 2 (aerobic)'),
                            'itemStyle' => [
                                'color' => HeartRateZone::COLORS[HeartRateZone::TWO],
                            ],
                        ],
                        [
                            'value' => $this->timeInHeartRateZones->getTimeInZoneThree(),
                            'name' => $this->translator->trans('Zone 3 (aerobic/anaerobic)'),
                            'itemStyle' => [
                                'color' => HeartRateZone::COLORS[HeartRateZone::THREE],
                            ],
                        ],
                        [
                            'value' => $this->timeInHeartRateZones->getTimeInZoneFour(),
                            'name' => $this->translator->trans('Zone 4 (anaerobic)'),
                            'itemStyle' => [
                                'color' => HeartRateZone::COLORS[HeartRateZone::FOUR],
                            ],
                        ],
                        [
                            'value' => $this->timeInHeartRateZones->getTimeInZoneFive(),
                            'name' => $this->translator->trans('Zone 5 (maximal)'),
                            'itemStyle' => [
                                'color' => HeartRateZone::COLORS[HeartRateZone::FIVE],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

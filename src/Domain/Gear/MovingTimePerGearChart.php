<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Theme\Theme;
use App\Infrastructure\Serialization\Escape;

final readonly class MovingTimePerGearChart
{
    private function __construct(
        /** @var array<string, int> */
        private array $movingTimePerGear,
        private Gears $gears,
        private Theme $theme,
    ) {
    }

    /**
     * @param array<string, int> $movingTimePerGear
     */
    public static function create(
        array $movingTimePerGear,
        Gears $gears,
        Theme $theme,
    ): self {
        return new self(
            movingTimePerGear: $movingTimePerGear,
            gears: $gears,
            theme: $theme,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = [];
        foreach ($this->movingTimePerGear as $gearId => $time) {
            if (!($gear = $this->gears->getByGearId(GearId::fromString($gearId))) instanceof Gear) {
                continue;
            }
            $data[] = [
                'value' => round($time / 3600),
                'name' => Escape::forJsonEncode($gear->getName()),
                'itemStyle' => [
                    'color' => $this->theme->getColorForGear($gear->getId()),
                ],
            ];
        }

        return [
            'backgroundColor' => null,
            'animation' => false,
            'grid' => [
                'left' => '2px',
                'right' => '0%',
                'bottom' => '0%',
                'containLabel' => true,
            ],
            'center' => ['50%', '50%'],
            'legend' => [
                'show' => false,
            ],
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => '{b}: {c}h',
            ],
            'series' => [
                [
                    'type' => 'pie',
                    'itemStyle' => [
                        'borderColor' => '#fff',
                        'borderWidth' => 2,
                    ],
                    'label' => [
                        'formatter' => "{gear|{b}}\n{sub|{c}h}",
                        'lineHeight' => 15,
                        'rich' => [
                            'gear' => [
                                'fontWeight' => 'bold',
                            ],
                            'sub' => [
                                'fontSize' => 12,
                            ],
                        ],
                    ],
                    'data' => $data,
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Infrastructure\ECharts\EChartsCallback;

final readonly class ActivityLocationsChart
{
    private function __construct(
        /** @var array<int,array{0: float, 1: float, 2: int}> */
        private array $activityLocations,
    ) {
    }

    /**
     * @param array<int,array{0: float, 1: float, 2: int}> $activityLocations
     */
    public static function create(
        array $activityLocations,
    ): self {
        return new self($activityLocations);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Most active location is first in the array, use that one to center the map.
        $center = $this->activityLocations[0];

        return [
            'tooltip' => [
                'show' => true,
            ],
            'geo' => [
                'tooltip' => [
                    'show' => true,
                ],
                'silent' => true,
                'center' => [$center[0], $center[1]],
                'zoom' => 15,
                'map' => 'world',
                'roam' => false,
            ],
            'series' => [
                'type' => 'effectScatter',
                'coordinateSystem' => 'geo',
                'symbolSize' => EChartsCallback::symbolSize(),
                'itemStyle' => [
                    'color' => '#E34902',
                ],
                'encode' => [
                    'tooltip' => 2,
                ],
                'data' => $this->activityLocations,
            ],
        ];
    }
}

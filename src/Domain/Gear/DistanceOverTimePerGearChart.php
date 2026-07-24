<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Gear\FindGearStatsPerDay\FindGearStatsPerDayResponse;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Escape;
use App\Infrastructure\Theme\Theme;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DistanceOverTimePerGearChart
{
    private function __construct(
        private Gears $gears,
        private FindGearStatsPerDayResponse $gearStats,
        private SerializableDateTime $startDate,
        private UnitSystem $unitSystem,
        private TranslatorInterface $translator,
        private SerializableDateTime $now,
    ) {
    }

    public static function create(
        Gears $gears,
        FindGearStatsPerDayResponse $gearStats,
        SerializableDateTime $startDate,
        UnitSystem $unitSystem,
        TranslatorInterface $translator,
        SerializableDateTime $now,
    ): self {
        return new self(
            gears: $gears,
            gearStats: $gearStats,
            startDate: $startDate,
            unitSystem: $unitSystem,
            translator: $translator,
            now: $now
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $distanceOverTimePerGear = [];
        $gears = $this->gears->sortByIsRetired();

        $period = new \DatePeriod(
            start: $this->startDate,
            interval: new \DateInterval('P1D'),
            end: $this->now
        );

        foreach ($gears as $gear) {
            $previousDistance = Kilometer::zero()->toUnitSystem($this->unitSystem);
            foreach ($period as $date) {
                $date = SerializableDateTime::fromDateTimeImmutable($date);

                if (!$distance = $this->gearStats->getDistanceFor($gear->getId(), $date)?->toUnitSystem($this->unitSystem)) {
                    $distance = $previousDistance;
                }
                $previousDistance = $distance;
                $distanceOverTimePerGear[(string) $gear->getId()][] = [$date->format('Y-m-d'), round($distance->toInt())];
            }
        }

        $series = [];
        $selectedSeries = [];

        foreach ($gears as $gear) {
            $gearName = Escape::forJsonEncode($gear->getName());
            $selectedSeries[$gearName] = !$gear->isRetired();

            $series[] = [
                'name' => $gearName,
                'type' => 'line',
                'smooth' => true,
                'showSymbol' => false,
                'itemStyle' => [
                    'color' => Theme::getColorForGear($gear->getId()),
                ],
                'data' => $distanceOverTimePerGear[(string) $gear->getId()],
            ];
        }

        arsort($selectedSeries, SORT_NUMERIC);

        return [
            'animation' => true,
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '50px',
                'containLabel' => true,
            ],
            'tooltip' => [
                'show' => true,
                'trigger' => 'axis',
            ],
            'legend' => [
                'selected' => $selectedSeries,
                'data' => array_map(
                    fn (string $gearName): array => [
                        'name' => $gearName,
                    ],
                    array_keys($selectedSeries),
                ),
                'type' => 'scroll',
                'pageButtonItemGap' => 2,
                'pageIconSize' => 10,
            ],
            'dataZoom' => [
                [
                    'type' => 'slider',
                    'start' => 0,
                    'end' => 100,
                    'brushSelect' => true,
                    'zoomLock' => false,
                    'zoomOnMouseWheel' => false,
                    'labelFormatter' => '',
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'time',
                    'axisLabel' => [
                        'formatter' => [
                            'year' => '{yyyy}',
                            'month' => '{MMM}',
                            'day' => '{d} {MMM}',
                            'hour' => '',
                            'minute' => '',
                            'second' => '',
                            'millisecond' => '',
                            'none' => '',
                        ],
                    ],
                    'axisTick' => [
                        'show' => false,
                    ],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => $this->translator->trans('Distance in {unit}', ['{unit}' => Kilometer::zero()->toUnitSystem($this->unitSystem)->getSymbol()]),
                    'nameRotate' => 90,
                    'nameLocation' => 'middle',
                    'nameGap' => 50,
                ],
            ],
            'series' => $series,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\ActivityGrid;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityGridType: string implements TranslatableInterface
{
    case LOAD = 'load';
    case MOVING_TIME = 'movingTime';
    case CALORIES_BURNED = 'caloriesBurned';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::LOAD => $translator->trans('Load', locale: $locale),
            self::MOVING_TIME => $translator->trans('Moving time', locale: $locale),
            self::CALORIES_BURNED => $translator->trans('Calories', locale: $locale),
        };
    }

    public function getSvgIcon(): string
    {
        return match ($this) {
            self::MOVING_TIME => 'time',
            self::LOAD => 'power',
            self::CALORIES_BURNED => 'calories',
        };
    }

    /**
     * @return array<int, array{min?: int|float, max?: int|float, value?: int, color: string, label: string}>
     */
    public function getPieces(TranslatorInterface $translator): array
    {
        return match ($this) {
            self::LOAD => [
                [
                    'value' => -1,
                    'color' => '#9CA3AF',
                    'label' => $translator->trans('No activities'),
                ],
                [
                    'min' => 0,
                    'max' => 50,
                    'color' => '#68B34B',
                    'label' => $translator->trans('Low').' (0 - 50)',
                ],
                [
                    'min' => 50.01,
                    'max' => 100,
                    'color' => '#FAB735',
                    'label' => $translator->trans('Medium').' (50 - 100)',
                ],
                [
                    'min' => 100.01,
                    'max' => 150,
                    'color' => '#FF8E14',
                    'label' => $translator->trans('High').' (100 - 150)',
                ],
                [
                    'min' => 150.01,
                    'color' => '#FF0C0C',
                    'label' => $translator->trans('Very high').' (> 150)',
                ],
            ],
            self::MOVING_TIME => [
                [
                    'min' => 0,
                    'max' => 0,
                    'color' => '#9CA3AF',
                    'label' => $translator->trans('No activities'),
                ],
                [
                    'min' => 0.01,
                    'max' => 30,
                    'color' => '#68B34B',
                    'label' => $translator->trans('Low').' (0 - 30m)',
                ],
                [
                    'min' => 30.01,
                    'max' => 60,
                    'color' => '#FAB735',
                    'label' => $translator->trans('Medium').' (31m - 1h)',
                ],
                [
                    'min' => 60.01,
                    'max' => 120,
                    'color' => '#FF8E14',
                    'label' => $translator->trans('High').' (1h - 2h)',
                ],
                [
                    'min' => 120.01,
                    'color' => '#FF0C0C',
                    'label' => $translator->trans('Very high').' (> 2h)',
                ],
            ],
            self::CALORIES_BURNED => [
                [
                    'min' => 0,
                    'max' => 0,
                    'color' => '#9CA3AF',
                    'label' => $translator->trans('No activities'),
                ],
                [
                    'min' => 0.01,
                    'max' => 500,
                    'color' => '#68B34B',
                    'label' => $translator->trans('Low').' (0 - 500)',
                ],
                [
                    'min' => 500.01,
                    'max' => 750,
                    'color' => '#FAB735',
                    'label' => $translator->trans('Medium').' (501 - 750)',
                ],
                [
                    'min' => 750.01,
                    'max' => 1000,
                    'color' => '#FF8E14',
                    'label' => $translator->trans('High').' (751 - 1000)',
                ],
                [
                    'min' => 1000.01,
                    'color' => '#FF0C0C',
                    'label' => $translator->trans('Very high').' (> 1000)',
                ],
            ],
        };
    }
}

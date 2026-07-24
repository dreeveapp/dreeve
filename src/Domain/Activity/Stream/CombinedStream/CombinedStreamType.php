<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\UnitSystem;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CombinedStreamType: string implements TranslatableInterface
{
    case ALTITUDE = 'altitude';
    case WATTS = 'watts';
    case CADENCE = 'cadence';
    case STEPS_PER_MINUTE = 'spm';
    case HEART_RATE = 'heartrate';
    case VELOCITY = 'velocity';
    case PACE = 'pace';
    case DISTANCE = 'distance';
    case LAT_LNG = 'latlng';
    case TIME = 'time';
    case GRADE = 'grade';

    public function getStreamType(): StreamType
    {
        return match ($this) {
            CombinedStreamType::PACE, CombinedStreamType::VELOCITY => StreamType::VELOCITY,
            CombinedStreamType::STEPS_PER_MINUTE => StreamType::CADENCE,
            CombinedStreamType::GRADE => StreamType::GRADE,
            default => StreamType::from($this->value),
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            CombinedStreamType::DISTANCE => $translator->trans('Distance'),
            CombinedStreamType::ALTITUDE => $translator->trans('Elevation'),
            CombinedStreamType::HEART_RATE => $translator->trans('Heart rate'),
            CombinedStreamType::CADENCE,
            CombinedStreamType::STEPS_PER_MINUTE => $translator->trans('Cadence'),
            CombinedStreamType::WATTS => $translator->trans('Power'),
            CombinedStreamType::PACE => $translator->trans('Pace'),
            CombinedStreamType::VELOCITY => $translator->trans('Speed'),
            default => throw new \RuntimeException(sprintf('Cannot translate CombinedStreamType "%s"', $this->value)),
        };
    }

    public function getSuffix(UnitSystem $unitSystem): string
    {
        return match ($this) {
            CombinedStreamType::HEART_RATE => 'bpm',
            CombinedStreamType::CADENCE => 'rpm',
            CombinedStreamType::STEPS_PER_MINUTE => 'spm',
            CombinedStreamType::WATTS => 'watt',
            CombinedStreamType::PACE => $unitSystem->paceSymbol(),
            CombinedStreamType::ALTITUDE => $unitSystem->elevationSymbol(),
            CombinedStreamType::VELOCITY => $unitSystem->speedSymbol(),
            default => throw new \RuntimeException('Suffix not supported for '.$this->value),
        };
    }

    public function getSeriesColor(): string
    {
        return match ($this) {
            CombinedStreamType::ALTITUDE => '#a6a6a6',
            CombinedStreamType::HEART_RATE => '#ee6666',
            CombinedStreamType::CADENCE,
            CombinedStreamType::STEPS_PER_MINUTE => '#91cc75',
            CombinedStreamType::WATTS => '#73c0de',
            CombinedStreamType::PACE,
            CombinedStreamType::VELOCITY => '#fac858',
            default => '#cccccc',
        };
    }

    public function isChartable(): bool
    {
        return match ($this) {
            self::DISTANCE, self::LAT_LNG, self::TIME, self::GRADE => false,
            default => true,
        };
    }
}

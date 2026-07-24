<?php

declare(strict_types=1);

namespace App\Infrastructure\Measurement;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Minute;
use Respect\Validation\Rules\Core\Simple;

trait ProvideUnitFromScalar
{
    public const string KILOMETER = 'km';
    public const string METER = 'm';
    public const string MILES = 'mi';
    public const string FOOT = 'ft';
    public const string HOUR = 'hour';
    public const string MINUTE = 'minute';
    public const string SIMPLE = 'simple'; // This is used for "SimpleUnit", units without a symbol

    public static function createUnitFromScalars(float $value, string $unit): Unit
    {
        return match ($unit) {
            self::KILOMETER => Kilometer::from($value),
            self::METER => Meter::from($value),
            self::MILES => Mile::from($value),
            self::FOOT => Foot::from($value),
            self::HOUR => Hour::from($value),
            self::MINUTE => Minute::from($value),
            self::SIMPLE => SimpleUnit::from($value),
            default => throw new \InvalidArgumentException('Invalid unit '.$unit),
        };
    }
}

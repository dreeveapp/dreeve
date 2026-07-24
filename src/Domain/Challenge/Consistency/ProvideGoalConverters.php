<?php

declare(strict_types=1);

namespace App\Domain\Challenge\Consistency;

use App\Infrastructure\Measurement\Length\Foot;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\Mile;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Minute;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Unit;

trait ProvideGoalConverters
{
    abstract public function getGoal(): Unit;

    public function convertKilometerToGoalUnit(Kilometer $kilometer): Unit
    {
        if ($this->getGoal() instanceof Kilometer) {
            return $kilometer;
        }
        if ($this->getGoal() instanceof Meter) {
            return $kilometer->toMeter();
        }
        if ($this->getGoal() instanceof Mile) {
            return $kilometer->toMiles();
        }
        if ($this->getGoal() instanceof Foot) {
            return $kilometer->toMeter()->toFoot();
        }

        throw new \RuntimeException(sprintf('Cannot convert Kilometer to %s', $this->getGoal()::class));
    }

    public function convertMeterToGoalUnit(Meter $meter): Unit
    {
        if ($this->getGoal() instanceof Meter) {
            return $meter;
        }
        if ($this->getGoal() instanceof Kilometer) {
            return $meter->toKilometer();
        }
        if ($this->getGoal() instanceof Mile) {
            return $meter->toKilometer()->toMiles();
        }
        if ($this->getGoal() instanceof Foot) {
            return $meter->toFoot();
        }

        throw new \RuntimeException(sprintf('Cannot convert Meter to %s', $this->getGoal()::class));
    }

    public function convertSecondsToGoalUnit(Seconds $seconds): Unit
    {
        if ($this->getGoal() instanceof Hour) {
            return $seconds->toHour();
        }
        if ($this->getGoal() instanceof Minute) {
            return $seconds->toMinute();
        }

        throw new \RuntimeException(sprintf('Cannot convert Seconds to %s', $this->getGoal()::class));
    }
}

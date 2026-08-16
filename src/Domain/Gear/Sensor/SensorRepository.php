<?php

declare(strict_types=1);

namespace App\Domain\Gear\Sensor;

interface SensorRepository
{
    public function findAll(): Sensors;
}

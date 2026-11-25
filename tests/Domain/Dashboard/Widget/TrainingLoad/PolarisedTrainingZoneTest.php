<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZone;
use App\Tests\ContainerTestCase;

class PolarisedTrainingZoneTest extends ContainerTestCase
{
    public function testGetPercentageIn(): void
    {
        $timeInHeartRateZones = TimeInHeartRateZones::create(
            timeInZoneOne: 3000,
            timeInZoneTwo: 5000,
            timeInZoneThree: 1000,
            timeInZoneFour: 800,
            timeInZoneFive: 200,
        );

        self::assertSame(80.0, PolarisedTrainingZone::LOW->getPercentageIn($timeInHeartRateZones));
        self::assertSame(10.0, PolarisedTrainingZone::MODERATE->getPercentageIn($timeInHeartRateZones));
        self::assertSame(10.0, PolarisedTrainingZone::HIGH->getPercentageIn($timeInHeartRateZones));
    }
}

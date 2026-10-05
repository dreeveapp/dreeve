<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZone;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function testGetRecommendedRange(): void
    {
        $actual = [];
        foreach (PolarisedTrainingZone::cases() as $zone) {
            $actual[$zone->name] = $zone->getRecommendedRange();
        }
        $this->assertEquals(
            ['LOW' => '75 - 90%', 'MODERATE' => '0 - 10%', 'HIGH' => '10 - 20%'],
            $actual,
        );
    }

    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (PolarisedTrainingZone::cases() as $zone) {
            $actual[$zone->name] = $zone->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            ['LOW' => 'Z1-2 (Low)', 'MODERATE' => 'Z3 (Mod)', 'HIGH' => 'Z4-5 (High)'],
            $actual,
        );
    }
}

<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZone;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class PolarisedTrainingZoneTest extends ContainerTestCase
{
    use MatchesSnapshots;

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
        $snapshot = [];
        foreach (PolarisedTrainingZone::cases() as $zone) {
            $snapshot[$zone->name] = $zone->getRecommendedRange();
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (PolarisedTrainingZone::cases() as $zone) {
            $snapshot[$zone->name] = $zone->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }
}

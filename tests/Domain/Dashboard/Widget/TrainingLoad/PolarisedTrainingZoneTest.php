<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Dashboard\Widget\TrainingLoad\PolarisedTrainingZone;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class PolarisedTrainingZoneTest extends ContainerTestCase
{
    use MatchesSnapshots;

    #[DataProvider(methodName: 'isWithinRecommendedRangeProvider')]
    public function testIsWithinRecommendedRange(PolarisedTrainingZone $zone, float $percentage, bool $expected): void
    {
        self::assertSame($expected, $zone->isWithinRecommendedRange($percentage));
    }

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

    public static function isWithinRecommendedRangeProvider(): iterable
    {
        // LOW: 75 - 90%
        yield 'low below range' => [PolarisedTrainingZone::LOW, 74.99, false];
        yield 'low lower boundary' => [PolarisedTrainingZone::LOW, 75.0, true];
        yield 'low middle' => [PolarisedTrainingZone::LOW, 76.81, true];
        yield 'low upper boundary' => [PolarisedTrainingZone::LOW, 90.0, true];
        yield 'low above range' => [PolarisedTrainingZone::LOW, 90.01, false];

        // MODERATE: 0 - 10%
        yield 'moderate lower boundary' => [PolarisedTrainingZone::MODERATE, 0.0, true];
        yield 'moderate upper boundary' => [PolarisedTrainingZone::MODERATE, 10.0, true];
        yield 'moderate above range' => [PolarisedTrainingZone::MODERATE, 13.44, false];

        // HIGH: 10 - 20%
        yield 'high below range' => [PolarisedTrainingZone::HIGH, 9.76, false];
        yield 'high lower boundary' => [PolarisedTrainingZone::HIGH, 10.0, true];
        yield 'high upper boundary' => [PolarisedTrainingZone::HIGH, 20.0, true];
        yield 'high above range' => [PolarisedTrainingZone::HIGH, 20.01, false];
    }
}

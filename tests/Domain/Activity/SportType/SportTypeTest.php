<?php

namespace App\Tests\Domain\Activity\SportType;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class SportTypeTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testGetTemplateName(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->getTemplateName();
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetVelocityDisplayPreference(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->getVelocityDisplayPreference()::class;
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetDistanceDisplayPreference(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->getDistanceDisplayPreference()::class;
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testSupportsShiftingStats(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->supportsShiftingStats();
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testSupportsSplits(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->supportsSplits();
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetDisplaySymbols(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            foreach (UnitSystem::cases() as $unitSystem) {
                $snapshot[$sportType->value][$unitSystem->value] = [
                    'distance' => $sportType->distanceSymbol($unitSystem),
                    'speed' => $sportType->speedSymbol($unitSystem),
                    'distancePrecision' => $sportType->getDistancePrecision(),
                ];
            }
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetSingularTranslations(): void
    {
        $snapshot = [];
        foreach (SportType::cases() as $sportType) {
            $snapshot[$sportType->value] = $sportType->transSingular($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }
}

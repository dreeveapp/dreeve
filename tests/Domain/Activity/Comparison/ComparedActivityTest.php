<?php

namespace App\Tests\Domain\Activity\Comparison;

use App\Domain\Activity\Comparison\ComparedActivity;
use App\Domain\Activity\Comparison\ComparisonMetric;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\EnrichedActivityBuilder;
use PHPUnit\Framework\TestCase;

class ComparedActivityTest extends TestCase
{
    public function testGetEfficiencyFactor(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(ActivityBuilder::fromDefaults()->withAverageHeartRate(125)->build())
                ->withNormalizedPower(250)
                ->build()
        );

        $this->assertEquals(2.0, $comparedActivity->getEfficiencyFactor());
    }

    public function testGetEfficiencyFactorWithoutNormalizedPower(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(ActivityBuilder::fromDefaults()->withAverageHeartRate(125)->build())
                ->build()
        );

        $this->assertNull($comparedActivity->getEfficiencyFactor());
    }

    public function testGetEfficiencyFactorWithoutHeartRate(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withNormalizedPower(250)->build()
        );

        $this->assertNull($comparedActivity->getEfficiencyFactor());
    }

    public function testGetEfficiencyFactorWithAHeartRateOfZero(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()
                ->withActivity(ActivityBuilder::fromDefaults()->withAverageHeartRate(0)->build())
                ->withNormalizedPower(250)
                ->build()
        );

        $this->assertNull($comparedActivity->getEfficiencyFactor());
    }

    public function testGetSpeedToPowerRatio(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withAveragePower(200)
                    ->withAverageSpeed(KmPerHour::from(30))
                    ->build()
            )->build()
        );

        $this->assertEquals(0.15, $comparedActivity->getSpeedToPowerRatio(UnitSystem::METRIC));
        $this->assertEquals(0.0932, $comparedActivity->getSpeedToPowerRatio(UnitSystem::IMPERIAL));
    }

    public function testGetSpeedToPowerRatioWithoutPower(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()->withAverageSpeed(KmPerHour::from(30))->build()
            )->build()
        );

        $this->assertNull($comparedActivity->getSpeedToPowerRatio(UnitSystem::METRIC));
    }

    public function testGetSpeedToPowerRatioWithAPowerOfZero(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withAveragePower(0)
                    ->withAverageSpeed(KmPerHour::from(30))
                    ->build()
            )->build()
        );

        $this->assertNull($comparedActivity->getSpeedToPowerRatio(UnitSystem::METRIC));
    }

    public function testGetValueForConvertsToTheUnitSystem(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withAverageSpeed(KmPerHour::from(30))
                    ->withElevation(Meter::from(1000))
                    ->build()
            )->build()
        );

        $this->assertEquals(30.0, $comparedActivity->getValueFor(ComparisonMetric::AVERAGE_SPEED, UnitSystem::METRIC));
        $this->assertEquals(18.64, $comparedActivity->getValueFor(ComparisonMetric::AVERAGE_SPEED, UnitSystem::IMPERIAL));
        $this->assertEquals(1000.0, $comparedActivity->getValueFor(ComparisonMetric::ELEVATION, UnitSystem::METRIC));
        $this->assertEquals(3280.5, $comparedActivity->getValueFor(ComparisonMetric::ELEVATION, UnitSystem::IMPERIAL));
    }

    public function testGetValueForEveryMetricWithoutAnyOptionalData(): void
    {
        $comparedActivity = ComparedActivity::fromEnrichedActivity(
            EnrichedActivityBuilder::fromDefaults()->withActivity(
                ActivityBuilder::fromDefaults()->withMovingTimeInSeconds(3600)->build()
            )->build()
        );

        $values = [];
        foreach (ComparisonMetric::cases() as $metric) {
            $values[$metric->value] = $comparedActivity->getValueFor($metric, UnitSystem::METRIC);
        }

        $this->assertEquals(
            [
                'movingTime' => 3600.0,
                'averageSpeed' => 0.0,
                'normalizedPower' => null,
                'averagePower' => null,
                'averageHeartRate' => null,
                'calories' => 0.0,
                'kilojoules' => null,
                'elevation' => 0.0,
                'speedToPowerRatio' => null,
                'efficiencyFactor' => null,
            ],
            $values
        );
    }
}

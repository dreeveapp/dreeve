<?php

namespace App\Tests\Domain\Gear;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use Money\Money;
use PHPUnit\Framework\TestCase;

class GearTest extends TestCase
{
    public function testGetMovingTimeFormatted(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withMovingTime(Seconds::from(3661))
            ->build();

        $this->assertEquals('1h 1m', $gear->getMovingTimeFormatted());
    }

    public function testGetMovingTimeFormattedWithZero(): void
    {
        $gear = GearBuilder::fromDefaults()->build();

        $this->assertEquals('0m', $gear->getMovingTimeFormatted());
    }

    public function testGetMovingTimeInHours(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withMovingTime(Seconds::from(7200))
            ->build();

        $this->assertEquals(2.0, $gear->getMovingTimeInHours()->toFloat());
    }

    public function testGetAverageDistance(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(30000))
            ->withNumberOfActivities(3)
            ->build();

        $this->assertEquals(Kilometer::from(10), $gear->getAverageDistance());
    }

    public function testGetAverageDistanceWithZeroActivities(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(30000))
            ->build();

        $this->assertEquals(Kilometer::zero(), $gear->getAverageDistance());
    }

    public function testGetAverageSpeed(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->withMovingTime(Seconds::from(3600))
            ->build();

        $this->assertEquals(KmPerHour::from(10), $gear->getAverageSpeed());
    }

    public function testGetAverageSpeedWithZeroMovingTime(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withDistanceInMeter(Meter::from(10000))
            ->build();

        $this->assertEquals(KmPerHour::zero(), $gear->getAverageSpeed());
    }

    public function testGetRelativeCostPerHour(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withMovingTime(Seconds::from(7200))
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertEquals(Money::EUR(5000), $gear->getRelativeCostPerHour());
    }

    public function testGetRelativeCostPerHourWithZeroMovingTime(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertEquals(Money::EUR(10000), $gear->getRelativeCostPerHour());
    }

    public function testGetRelativeCostPerHourWithoutPurchasePrice(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withMovingTime(Seconds::from(7200))
            ->build();

        $this->assertNull($gear->getRelativeCostPerHour());
    }

    public function testGetRelativeCostPerWorkout(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withNumberOfActivities(5)
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertEquals(Money::EUR(2000), $gear->getRelativeCostPerWorkout());
    }

    public function testGetRelativeCostPerWorkoutWithZeroActivities(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertEquals(Money::EUR(10000), $gear->getRelativeCostPerWorkout());
    }

    public function testGetRelativeCostPerWorkoutWithoutPurchasePrice(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withNumberOfActivities(5)
            ->build();

        $this->assertNull($gear->getRelativeCostPerWorkout());
    }
}

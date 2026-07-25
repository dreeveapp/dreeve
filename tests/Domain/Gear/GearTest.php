<?php

namespace App\Tests\Domain\Gear;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GearTest extends TestCase
{
    #[DataProvider('provideMovingTimeFormatted')]
    public function testGetMovingTimeFormatted(?Meter $distanceInMeter, ?Seconds $movingTime, string $expectedResult): void
    {
        $builder = GearBuilder::fromDefaults();
        if (null !== $distanceInMeter) {
            $builder = $builder->withDistanceInMeter($distanceInMeter);
        }
        if (null !== $movingTime) {
            $builder = $builder->withMovingTime($movingTime);
        }

        $this->assertEquals($expectedResult, $builder->build()->getMovingTimeFormatted());
    }

    public function testGetMovingTimeInHours(): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withMovingTime(Seconds::from(7200))
            ->build();

        $this->assertEquals(2.0, $gear->getMovingTimeInHours()->toFloat());
    }

    #[DataProvider('provideAverageDistance')]
    public function testGetAverageDistance(Meter $distanceInMeter, ?int $numberOfActivities, Kilometer $expectedResult): void
    {
        $builder = GearBuilder::fromDefaults()->withDistanceInMeter($distanceInMeter);
        if (null !== $numberOfActivities) {
            $builder = $builder->withNumberOfActivities($numberOfActivities);
        }

        $this->assertEquals($expectedResult, $builder->build()->getAverageDistance());
    }

    #[DataProvider('provideAverageSpeed')]
    public function testGetAverageSpeed(Meter $distanceInMeter, ?Seconds $movingTime, KmPerHour $expectedResult): void
    {
        $builder = GearBuilder::fromDefaults()->withDistanceInMeter($distanceInMeter);
        if (null !== $movingTime) {
            $builder = $builder->withMovingTime($movingTime);
        }

        $this->assertEquals($expectedResult, $builder->build()->getAverageSpeed());
    }

    #[DataProvider('provideRelativeCostPerHour')]
    public function testGetRelativeCostPerHour(?Seconds $movingTime, ?Money $purchasePrice, ?Money $expectedResult): void
    {
        $builder = GearBuilder::fromDefaults()->withPurchasePrice($purchasePrice);
        if (null !== $movingTime) {
            $builder = $builder->withMovingTime($movingTime);
        }
        $gear = $builder->build();

        if (null === $expectedResult) {
            $this->assertNull($gear->getRelativeCostPerHour());

            return;
        }

        $this->assertEquals($expectedResult, $gear->getRelativeCostPerHour());
    }

    #[DataProvider('provideRelativeCostPerWorkout')]
    public function testGetRelativeCostPerWorkout(?int $numberOfActivities, ?Money $purchasePrice, ?Money $expectedResult): void
    {
        $builder = GearBuilder::fromDefaults()->withPurchasePrice($purchasePrice);
        if (null !== $numberOfActivities) {
            $builder = $builder->withNumberOfActivities($numberOfActivities);
        }
        $gear = $builder->build();

        if (null === $expectedResult) {
            $this->assertNull($gear->getRelativeCostPerWorkout());

            return;
        }

        $this->assertEquals($expectedResult, $gear->getRelativeCostPerWorkout());
    }

    /**
     * @return iterable<string, array{?Meter, ?Seconds, string}>
     */
    public static function provideMovingTimeFormatted(): iterable
    {
        yield 'with moving time' => [Meter::from(10000), Seconds::from(3661), '1h 1m'];
        yield 'with zero moving time' => [null, null, '0m'];
    }

    /**
     * @return iterable<string, array{Meter, ?int, Kilometer}>
     */
    public static function provideAverageDistance(): iterable
    {
        yield 'with activities' => [Meter::from(30000), 3, Kilometer::from(10)];
        yield 'with zero activities' => [Meter::from(30000), null, Kilometer::zero()];
    }

    /**
     * @return iterable<string, array{Meter, ?Seconds, KmPerHour}>
     */
    public static function provideAverageSpeed(): iterable
    {
        yield 'with moving time' => [Meter::from(10000), Seconds::from(3600), KmPerHour::from(10)];
        yield 'with zero moving time' => [Meter::from(10000), null, KmPerHour::zero()];
    }

    /**
     * @return iterable<string, array{?Seconds, ?Money, ?Money}>
     */
    public static function provideRelativeCostPerHour(): iterable
    {
        yield 'with moving time' => [Seconds::from(7200), Money::EUR(10000), Money::EUR(5000)];
        yield 'with zero moving time' => [null, Money::EUR(10000), Money::EUR(10000)];
        yield 'without purchase price' => [Seconds::from(7200), null, null];
    }

    /**
     * @return iterable<string, array{?int, ?Money, ?Money}>
     */
    public static function provideRelativeCostPerWorkout(): iterable
    {
        yield 'with activities' => [5, Money::EUR(10000), Money::EUR(2000)];
        yield 'with zero activities' => [null, Money::EUR(10000), Money::EUR(10000)];
        yield 'without purchase price' => [5, null, null];
    }
}

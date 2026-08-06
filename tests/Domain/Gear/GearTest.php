<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Activity\ActivityTypes;
use App\Domain\Gear\Gear;
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

    public static function provideMovingTimeFormatted(): iterable
    {
        yield 'with moving time' => [Meter::from(10000), Seconds::from(3661), '1h 1m'];
        yield 'with zero moving time' => [null, null, '0m'];
    }

    public static function provideAverageDistance(): iterable
    {
        yield 'with activities' => [Meter::from(30000), 3, Kilometer::from(10)];
        yield 'with zero activities' => [Meter::from(30000), null, Kilometer::zero()];
    }

    public static function provideAverageSpeed(): iterable
    {
        yield 'with moving time' => [Meter::from(10000), Seconds::from(3600), KmPerHour::from(10)];
        yield 'with zero moving time' => [Meter::from(10000), null, KmPerHour::zero()];
    }

    public function testItRecordsGearWasAddedOnCreate(): void
    {
        $this->assertCount(1, GearBuilder::fromDefaults()->buildAsNewlyCreated()->getRecordedEvents());
    }

    public function testItDoesNotRecordOnHydration(): void
    {
        $this->assertEmpty(GearBuilder::fromDefaults()->build()->getRecordedEvents());
    }

    #[DataProvider('provideUnchangedMutations')]
    public function testItDoesNotRecordWhenNothingChanged(\Closure $mutate): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withName('Existing gear')
            ->withIsRetired(false)
            ->withLocalImagePath('/gear/bike.jpg')
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertEmpty($mutate($gear)->getRecordedEvents());
    }

    #[DataProvider('provideChangedMutations')]
    public function testItRecordsGearWasUpdatedOnlyOnceWhenSomethingChanged(\Closure $mutate): void
    {
        $gear = GearBuilder::fromDefaults()
            ->withName('Existing gear')
            ->withIsRetired(false)
            ->withLocalImagePath('/gear/bike.jpg')
            ->withPurchasePrice(Money::EUR(10000))
            ->build();

        $this->assertCount(1, $mutate($gear)->getRecordedEvents());
    }

    public function testItDoesNotRecordWhenTheActivityTypesAreEnriched(): void
    {
        $gear = GearBuilder::fromDefaults()->build();

        $this->assertEmpty($gear->withActivityTypes(ActivityTypes::empty())->getRecordedEvents());
    }

    public static function provideUnchangedMutations(): iterable
    {
        yield 'name' => [fn (Gear $gear): Gear => $gear->withName('Existing gear')];
        yield 'isRetired' => [fn (Gear $gear): Gear => $gear->withIsRetired(false)];
        yield 'localImagePath' => [fn (Gear $gear): Gear => $gear->withLocalImagePath('/gear/bike.jpg')];
        yield 'purchasePrice' => [fn (Gear $gear): Gear => $gear->withPurchasePrice(Money::EUR(10000))];
        yield 'all of them, like a no-op import' => [fn (Gear $gear): Gear => $gear
            ->withName('Existing gear')
            ->withIsRetired(false), ];
    }

    public static function provideChangedMutations(): iterable
    {
        yield 'name' => [fn (Gear $gear): Gear => $gear->withName('Renamed gear')];
        yield 'isRetired' => [fn (Gear $gear): Gear => $gear->withIsRetired(true)];
        yield 'localImagePath' => [fn (Gear $gear): Gear => $gear->withLocalImagePath('/gear/other.jpg')];
        yield 'localImagePath removed' => [fn (Gear $gear): Gear => $gear->withLocalImagePath(null)];
        yield 'purchasePrice amount' => [fn (Gear $gear): Gear => $gear->withPurchasePrice(Money::EUR(20000))];
        yield 'purchasePrice currency' => [fn (Gear $gear): Gear => $gear->withPurchasePrice(Money::USD(10000))];
    }

    public static function provideRelativeCostPerHour(): iterable
    {
        yield 'with moving time' => [Seconds::from(7200), Money::EUR(10000), Money::EUR(5000)];
        yield 'with zero moving time' => [null, Money::EUR(10000), Money::EUR(10000)];
        yield 'without purchase price' => [Seconds::from(7200), null, null];
    }

    public static function provideRelativeCostPerWorkout(): iterable
    {
        yield 'with activities' => [5, Money::EUR(10000), Money::EUR(2000)];
        yield 'with zero activities' => [null, Money::EUR(10000), Money::EUR(10000)];
        yield 'without purchase price' => [5, null, null];
    }
}

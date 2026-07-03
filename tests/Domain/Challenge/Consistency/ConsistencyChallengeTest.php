<?php

namespace App\Tests\Domain\Challenge\Consistency;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Challenge\Consistency\ChallengeConsistencyType;
use App\Domain\Challenge\Consistency\ConsistencyChallenge;
use App\Infrastructure\ValueObject\Measurement\Length\Foot;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Length\Mile;
use App\Infrastructure\ValueObject\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\Measurement\Time\Minute;
use App\Infrastructure\ValueObject\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Measurement\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsistencyChallengeTest extends TestCase
{
    public function testFromItShouldThrowWhenInvalidUnit(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid unit gram'));

        ConsistencyChallenge::createUnitFromScalars(0, 'gram');
    }

    #[DataProvider(methodName: 'provideDataForUnitConversion')]
    public function testConvertKilometerToUnit(ConsistencyChallenge $challenge, Unit $expectedConversion): void
    {
        $this->assertEquals(
            $expectedConversion,
            $challenge->convertKilometerToGoalUnit(Kilometer::zero())
        );
    }

    public function testConvertKilometerToUnitItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Cannot convert Kilometer to App\Infrastructure\ValueObject\Measurement\Time\Hour'));
        ConsistencyChallenge::create(
            label: 'label',
            type: ChallengeConsistencyType::DISTANCE,
            goal: 0,
            unit: 'hour',
            sportTypesToInclude: SportTypes::empty()
        )->convertKilometerToGoalUnit(Kilometer::zero());
    }

    #[DataProvider(methodName: 'provideDataForUnitConversion')]
    public function testConvertMeterToUnit(ConsistencyChallenge $challenge, Unit $expectedConversion): void
    {
        $this->assertEquals(
            $expectedConversion,
            $challenge->convertMeterToGoalUnit(Meter::zero())
        );
    }

    public function testConvertMeterToUnitItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Cannot convert Meter to App\Infrastructure\ValueObject\Measurement\Time\Hour'));
        ConsistencyChallenge::create(
            label: 'label',
            type: ChallengeConsistencyType::DISTANCE,
            goal: 0,
            unit: 'hour',
            sportTypesToInclude: SportTypes::empty()
        )->convertMeterToGoalUnit(Meter::zero());
    }

    #[DataProvider(methodName: 'provideDataForSecondsToUnitConversion')]
    public function testConvertSecondsToUnit(ConsistencyChallenge $challenge, Unit $expectedConversion): void
    {
        $this->assertEquals(
            $expectedConversion,
            $challenge->convertSecondsToGoalUnit(Seconds::zero())
        );
    }

    public function testConvertSecondsToUnitItShouldThrow(): void
    {
        $this->expectExceptionObject(new \RuntimeException('Cannot convert Seconds to App\Infrastructure\ValueObject\Measurement\Length\Meter'));
        ConsistencyChallenge::create(
            label: 'label',
            type: ChallengeConsistencyType::DISTANCE,
            goal: 0,
            unit: 'm',
            sportTypesToInclude: SportTypes::empty()
        )->convertSecondsToGoalUnit(Seconds::zero());
    }

    public static function provideDataForUnitConversion(): iterable
    {
        yield 'kilometer' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::DISTANCE,
                goal: 0,
                unit: 'km',
                sportTypesToInclude: SportTypes::empty()
            ),
            Kilometer::from(0),
        ];
        yield 'meter' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::DISTANCE,
                goal: 0,
                unit: 'm',
                sportTypesToInclude: SportTypes::empty()
            ),
            Meter::from(0),
        ];
        yield 'miles' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::DISTANCE,
                goal: 0,
                unit: 'mi',
                sportTypesToInclude: SportTypes::empty()
            ),
            Mile::from(0),
        ];
        yield 'foot' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::DISTANCE,
                goal: 0,
                unit: 'ft',
                sportTypesToInclude: SportTypes::empty()
            ),
            Foot::from(0),
        ];
    }

    public static function provideDataForSecondsToUnitConversion(): iterable
    {
        yield 'hour' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::MOVING_TIME,
                goal: 0,
                unit: 'hour',
                sportTypesToInclude: SportTypes::empty()
            ),
            Hour::from(0),
        ];
        yield 'minute' => [
            ConsistencyChallenge::create(
                label: 'label',
                type: ChallengeConsistencyType::DISTANCE,
                goal: 0,
                unit: 'minute',
                sportTypesToInclude: SportTypes::empty()
            ),
            Minute::from(0),
        ];
    }
}

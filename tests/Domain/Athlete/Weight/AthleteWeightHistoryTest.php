<?php

namespace App\Tests\Domain\Athlete\Weight;

use App\Domain\Athlete\Weight\AthleteWeight;
use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Measurement\Mass\Kilogram;
use App\Infrastructure\ValueObject\Measurement\Mass\Pound;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class AthleteWeightHistoryTest extends TestCase
{
    public function testFind(): void
    {
        $weightHistory = AthleteWeightHistory::fromArray([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-02-02', 'weight' => 221],
            ['on' => '2024-04-04', 'weight' => 223],
            ['on' => '2024-03-03', 'weight' => 222],
        ], UnitSystem::METRIC);

        $this->assertEquals(
            AthleteWeight::fromState(
                on: SerializableDateTime::fromString('2024-04-04'),
                weight: Kilogram::from(223),
            ),
            $weightHistory->find(SerializableDateTime::fromString('2024-04-04'))
        );
        $this->assertEquals(
            AthleteWeight::fromState(
                on: SerializableDateTime::fromString('2024-04-04'),
                weight: Kilogram::from(223),
            ),
            $weightHistory->find(SerializableDateTime::fromString('2025-01-01'))
        );
    }

    public function testFindImperial(): void
    {
        $weightHistory = AthleteWeightHistory::fromArray([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-02-02', 'weight' => 221],
            ['on' => '2024-04-04', 'weight' => 223],
            ['on' => '2024-03-03', 'weight' => 222],
        ], UnitSystem::IMPERIAL);

        $this->assertEquals(
            AthleteWeight::fromState(
                on: SerializableDateTime::fromString('2024-04-04'),
                weight: Pound::from(223),
            ),
            $weightHistory->find(SerializableDateTime::fromString('2024-04-04'))
        );
        $this->assertEquals(
            AthleteWeight::fromState(
                on: SerializableDateTime::fromString('2024-04-04'),
                weight: Pound::from(223),
            ),
            $weightHistory->find(SerializableDateTime::fromString('2025-01-01'))
        );
    }

    public function testFindShouldThrowWhenNoWeightIsRecordedBeforeTheGivenDate(): void
    {
        $weightHistory = AthleteWeightHistory::fromArray([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-02-02', 'weight' => 221],
        ], UnitSystem::METRIC);

        $this->expectExceptionObject(new EntityNotFound('AthleteWeight for date "2023-01-01 00:00:00" not found'));
        $weightHistory->find(SerializableDateTime::fromString('2023-01-01'));
    }

    public function testItShouldThrowOnInvalidWeight(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid weight "lol" set for athlete weightHistory in config.yaml file'));
        AthleteWeightHistory::fromArray([['on' => '2025-11-16', 'weight' => 'lol']], UnitSystem::METRIC);
    }

    public function testItShouldThrowOnInvalidDate(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date "YYYY-MM-DD" set for athlete weightHistory in config.yaml file'));
        AthleteWeightHistory::fromArray([['on' => 'YYYY-MM-DD', 'weight' => 220]], UnitSystem::METRIC);
    }
}

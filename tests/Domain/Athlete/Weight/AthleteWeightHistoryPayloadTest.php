<?php

namespace App\Tests\Domain\Athlete\Weight;

use App\Domain\Athlete\Weight\AthleteWeightHistoryPayload;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AthleteWeightHistoryPayloadTest extends TestCase
{
    public function testWith(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => 220],
        ]);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-04-04', 'weight' => 223.5],
        ], $payload->with(SerializableDateTime::fromString('2024-04-04'), 223.5)->toArray());
    }

    public function testWithItShouldReplaceTheWeightForTheDate(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-03-03', 'weight' => 222],
        ]);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-03-03', 'weight' => 100.5],
        ], $payload->with(SerializableDateTime::fromString('2024-03-03'), 100.5)->toArray());
    }

    public function testWithItShouldIgnoreTheTimeOfDay(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-03-03', 'weight' => 222],
        ]);

        $this->assertSame([
            ['on' => '2024-03-03', 'weight' => 100.5],
        ], $payload->with(SerializableDateTime::fromString('2024-03-03 10:22:22'), 100.5)->toArray());
    }

    public function testWithItShouldNotMutateThePayload(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => 220],
        ]);

        $payload->with(SerializableDateTime::fromString('2024-04-04'), 223.5);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => 220],
        ], $payload->toArray());
    }

    public function testWithout(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => 220],
            ['on' => '2024-03-03', 'weight' => 222],
        ]);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => 220],
        ], $payload->without(SerializableDateTime::fromString('2024-03-03'))->toArray());
    }

    public function testWithoutItShouldIgnoreUnknownDates(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => 220],
        ]);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => 220],
        ], $payload->without(SerializableDateTime::fromString('2024-03-03'))->toArray());
    }

    public function testItShouldKeepStoredEntriesUntouched(): void
    {
        $payload = AthleteWeightHistoryPayload::fromStoredValue([
            ['on' => '2024-01-01', 'weight' => '220'],
            ['on' => '2024-02-02', 'weight' => 221, 'note' => 'after breakfast'],
        ]);

        $this->assertSame([
            ['on' => '2024-01-01', 'weight' => '220'],
            ['on' => '2024-02-02', 'weight' => 221, 'note' => 'after breakfast'],
            ['on' => '2024-04-04', 'weight' => 223.5],
        ], $payload->with(SerializableDateTime::fromString('2024-04-04'), 223.5)->toArray());
    }

    #[DataProvider('provideEmptyStoredValues')]
    public function testFromStoredValueItShouldFallBackToAnEmptyHistory(mixed $storedValue): void
    {
        $this->assertSame(
            [['on' => '2024-04-04', 'weight' => 223.5]],
            AthleteWeightHistoryPayload::fromStoredValue($storedValue)
                ->with(SerializableDateTime::fromString('2024-04-04'), 223.5)
                ->toArray()
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideEmptyStoredValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'string' => ['nonsense'];
    }
}

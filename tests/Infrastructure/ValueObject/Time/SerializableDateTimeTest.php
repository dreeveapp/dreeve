<?php

namespace App\Tests\Infrastructure\ValueObject\Time;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SerializableDateTimeTest extends TestCase
{
    public function testFromString(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2023-10-05 10:22:22'),
            SerializableDateTime::fromString('2023-10-05 10:22:22')
        );
    }

    public function testCreateFromFormat(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2024-02-29 00:00:00'),
            SerializableDateTime::createFromFormat('!Y-m-d', '2024-02-29')
        );
    }

    #[DataProvider('provideInvalidDates')]
    public function testCreateFromFormatItShouldThrowOnInvalidDate(string $date): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(sprintf('Invalid date format !Y-m-d for %s', $date)));

        SerializableDateTime::createFromFormat('!Y-m-d', $date);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidDates(): iterable
    {
        yield 'non existing day' => ['2026-02-29'];
        yield 'out of range month and day' => ['2026-13-45'];
        yield 'other format' => ['08-09-2026'];
        yield 'relative date' => ['now'];
        yield 'trailing time' => ['2026-09-08 10:00:00'];
        yield 'empty' => [''];
    }

    public function testGetSecondsUntilMidnight(): void
    {
        $this->assertEquals(
            5258,
            SerializableDateTime::fromString('2023-10-05 22:32:22')->getSecondsUntilMidnight()
        );
        $this->assertEquals(
            86400,
            SerializableDateTime::fromString('2023-10-05 00:00:00')->getSecondsUntilMidnight()
        );
        $this->assertEquals(
            1,
            SerializableDateTime::fromString('2023-10-05 23:59:59')->getSecondsUntilMidnight()
        );
    }

    public function testFromTimeStamp(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2023-10-05 18:56:31'),
            SerializableDateTime::fromTimestamp(1696532191)
        );
    }

    public function testSerialize(): void
    {
        $date = SerializableDateTime::fromString('2023-10-05 10:22:22');
        $this->assertEquals(
            Json::encode($date),
            Json::encode((string) $date),
        );
    }

    public function testToUtc(): void
    {
        $this->assertEquals(
            SerializableDateTime::fromString('2023-10-05 10:22:22'),
            SerializableDateTime::fromString('2023-10-05 10:22:22')->toUtc(),
        );
    }

    public function testComparisons(): void
    {
        $earlier = SerializableDateTime::fromString('2023-10-05 10:00:00');
        $later = SerializableDateTime::fromString('2023-10-05 11:00:00');

        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
        $this->assertFalse($earlier->isBefore($earlier));

        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
        $this->assertFalse($earlier->isAfter($earlier));

        $this->assertTrue($earlier->isBeforeOrOn($later));
        $this->assertTrue($earlier->isBeforeOrOn($earlier));
        $this->assertFalse($later->isBeforeOrOn($earlier));

        $this->assertTrue($later->isAfterOrOn($earlier));
        $this->assertTrue($later->isAfterOrOn($later));
        $this->assertFalse($earlier->isAfterOrOn($later));
    }
}

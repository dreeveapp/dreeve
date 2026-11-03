<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\Time;

use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function testItShouldExposeFromAndTillDates(): void
    {
        $from = SerializableDateTime::fromString('25-02-1982');
        $till = SerializableDateTime::fromString('30-03-1982');
        $dateRange = DateRange::fromDates($from, $till);
        $this->assertEquals($from, $dateRange->getFrom());
        $this->assertEquals($till, $dateRange->getTill());
    }

    public function testLastXDays(): void
    {
        $now = SerializableDateTime::fromString('25-02-1982');
        $this->assertEquals(
            DateRange::fromDates(SerializableDateTime::fromString('15-02-1982'), $now),
            DateRange::lastXDays($now, 10),
        );
    }

    public function testItShouldThrowWhenTillComesBeforeFrom(): void
    {
        $from = SerializableDateTime::fromString('25-02-1982');
        $till = SerializableDateTime::fromString('30-03-1982');
        $this->expectExceptionObject(new \InvalidArgumentException('invalid DateRange: 1982-03-30 00:00:00 till 1982-02-25 00:00:00'));

        DateRange::fromDates($till, $from);
    }

    public function testGetNumberOfDays(): void
    {
        $this->assertEquals(
            5,
            DateRange::fromDates(
                SerializableDateTime::fromString('15-02-1982'),
                SerializableDateTime::fromString('19-02-1982')
            )->getNumberOfDays(),
        );
    }
}

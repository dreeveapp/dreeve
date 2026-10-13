<?php

namespace App\Tests\Domain\Calendar;

use App\Domain\Calendar\Month;
use App\Domain\Calendar\Months;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class MonthsTest extends TestCase
{
    public function testLastXMonths(): void
    {
        $now = SerializableDateTime::fromString('2023-10-31 16:15:04');

        $this->assertEquals(
            ['2022-11', '2022-12', '2023-01', '2023-02', '2023-03', '2023-04', '2023-05', '2023-06', '2023-07', '2023-08', '2023-09', '2023-10'],
            Months::lastXMonths(12, $now)->map(fn (Month $month): string => $month->getId()),
        );
        $this->assertEquals(
            ['2023-10'],
            Months::lastXMonths(1, $now)->map(fn (Month $month): string => $month->getId()),
        );
    }
}

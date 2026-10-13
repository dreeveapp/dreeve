<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\MonthlyUsage;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class MonthlyUsageTest extends TestCase
{
    public function testWasUsedSince(): void
    {
        $usage = MonthlyUsage::fromQueryResults([
            ['month' => '2023-06', 'distance' => 1000, 'firstUsedOn' => '2023-06-05 08:00:00', 'lastUsedOn' => '2023-06-20 09:00:00', 'sportTypes' => 'Ride'],
            ['month' => '2023-07', 'distance' => 1000, 'firstUsedOn' => '2023-07-02 10:00:00', 'lastUsedOn' => '2023-07-17 16:15:04', 'sportTypes' => 'Ride'],
        ]);

        $this->assertTrue($usage->wasUsedSince(SerializableDateTime::fromString('2023-07-01 00:00:00')));
        $this->assertTrue($usage->wasUsedSince(SerializableDateTime::fromString('2023-07-17 16:15:04')));
        $this->assertFalse($usage->wasUsedSince(SerializableDateTime::fromString('2023-07-17 16:15:05')));
    }
}

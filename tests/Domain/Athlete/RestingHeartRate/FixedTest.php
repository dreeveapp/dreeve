<?php

declare(strict_types=1);

namespace App\Tests\Domain\Athlete\RestingHeartRate;

use App\Domain\Athlete\RestingHeartRate\Fixed;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class FixedTest extends TestCase
{
    public function testFixed(): void
    {
        $fixed = new Fixed(20);

        $this->assertEquals(
            20,
            $fixed->calculate(3, SerializableDateTime::fromString('2020-01-01 00:00:00')),
        );
    }
}

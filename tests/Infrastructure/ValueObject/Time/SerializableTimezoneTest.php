<?php

namespace App\Tests\Infrastructure\ValueObject\Time;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use PHPUnit\Framework\TestCase;

class SerializableTimezoneTest extends TestCase
{
    public function testDefault(): void
    {
        $this->assertEquals(
            SerializableTimezone::fromString('Europe/Brussels'),
            SerializableTimezone::default()
        );
    }

    public function testItShouldThrowWhenEmpty(): void
    {
        $this->expectExceptionObject(new \RuntimeException('timezone cannot be empty'));
        SerializableTimezone::fromString(' ');
    }

    public function testFromOptionalString(): void
    {
        $this->assertNull(
            SerializableTimezone::fromOptionalString(''),
        );
    }

    public function testSerialize(): void
    {
        $timezone = SerializableTimezone::fromString('Europe/Brussels');
        $this->assertEquals(
            Json::encode($timezone),
            Json::encode((string) $timezone)
        );
    }
}

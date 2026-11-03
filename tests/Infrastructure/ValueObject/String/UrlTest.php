<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\ValueObject\String\Url;
use PHPUnit\Framework\TestCase;

class UrlTest extends TestCase
{
    public function testFromString(): void
    {
        $this->assertEquals(
            'https://google.com',
            (string) Url::fromString('https://google.com')
        );
    }

    public function testFromStringThrowsException(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid url "invalid"'));
        Url::fromString('invalid');
    }
}

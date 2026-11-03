<?php

declare(strict_types=1);

namespace App\Tests\Domain\Ftp;

use App\Domain\Ftp\FtpValue;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class FtpValueTest extends TestCase
{
    public function testItShouldThrowWhenInvalid(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Minimum FTP of 1 expected'));
        FtpValue::fromInt(0);
    }

    public function testToString(): void
    {
        $this->assertEquals(
            '200',
            (string) FtpValue::fromInt(200)
        );
    }

    public function testJsonSerialize(): void
    {
        $this->assertEquals(
            '"200"',
            Json::encode(FtpValue::fromInt(200))
        );
    }
}

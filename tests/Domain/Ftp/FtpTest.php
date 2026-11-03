<?php

declare(strict_types=1);

namespace App\Tests\Domain\Ftp;

use App\Domain\Ftp\Ftp;
use App\Domain\Ftp\FtpValue;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class FtpTest extends TestCase
{
    public function testGetRelativeFtpWhenWeightIsNull(): void
    {
        $ftp = Ftp::fromState(SerializableDateTime::some(), FtpValue::fromInt(200))->withAthleteWeight(null);
        $this->assertNull($ftp->getRelativeFtp());
    }
}

<?php

namespace App\Tests\Domain\Ftp;

use App\Domain\Activity\ActivityType;
use App\Domain\Ftp\Ftp;
use App\Domain\Ftp\FtpHistory;
use App\Domain\Ftp\Ftps;
use App\Domain\Ftp\FtpValue;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class FtpHistoryTest extends TestCase
{
    public function testItShouldBeBackwardsCompatible(): void
    {
        $this->assertEquals(
            FtpHistory::fromArray(['cycling' => [['on' => '2025-11-28', 'ftp' => 220]]]),
            FtpHistory::fromArray([['on' => '2025-11-28', 'ftp' => 220]]),
        );
    }

    public function testFromArray(): void
    {
        $this->assertEquals(
            Ftps::fromArray([Ftp::fromState(
                setOn: SerializableDateTime::fromString('2025-11-28'),
                ftp: FtpValue::fromInt(220),
            )]),
            FtpHistory::fromArray(['running' => [['on' => '2025-11-28', 'ftp' => 220]]])->findAll(ActivityType::RUN),
        );
        $this->assertEquals(
            Ftps::fromArray([Ftp::fromState(
                setOn: SerializableDateTime::fromString('2025-11-28'),
                ftp: FtpValue::fromInt(220),
            )]),
            FtpHistory::fromArray(['cycling' => [['on' => '2025-11-28', 'ftp' => 220]]])->findAll(ActivityType::RIDE),
        );
    }

    public function testFindItShouldThrowForUnsupportedActivityType(): void
    {
        $this->expectExceptionObject(new \RuntimeException('ActivityType "Walk" does not support FTP'));
        FtpHistory::fromArray(['cycling' => [['on' => '2025-11-28', 'ftp' => 220]]])->find(ActivityType::WALK, SerializableDateTime::fromString('2025-11-28'));
    }

    public function testFindAllItShouldThrowForUnsupportedActivityType(): void
    {
        $this->expectExceptionObject(new \RuntimeException('ActivityType "Walk" does not support FTP'));
        FtpHistory::fromArray(['cycling' => [['on' => '2025-11-28', 'ftp' => 220]]])->findAll(ActivityType::WALK);
    }

    public function testItShouldThrowOnInvalidDate(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid date "YYYY-MM-DD" set for athlete cycling ftpHistory in config.yaml file'));
        FtpHistory::fromArray([['on' => 'YYYY-MM-DD', 'ftp' => 220]]);
    }
}

<?php

namespace App\Tests\Infrastructure\Time\Format;

use App\Infrastructure\Time\Format\DateFormat;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class DateFormatTest extends TestCase
{
    public function testFrom(): void
    {
        $this->assertEquals(
            'DD., dd.MM.yy',
            (string) DateFormat::from('DD., dd.MM.yy')
        );
    }

    #[TestWith(['', 'Invalid date format provided. Format cannot be empty'])]
    #[TestWith(['EE RR b', 'Invalid date format provided "EE RR b", invalid format characters found: E, R, b'])]
    public function testFromItShouldThrowOnAnInvalidFormat(string $format, string $expectedMessage): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException($expectedMessage));
        DateFormat::from($format);
    }
}

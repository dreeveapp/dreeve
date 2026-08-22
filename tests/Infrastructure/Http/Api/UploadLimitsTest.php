<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Api;

use App\Infrastructure\Http\Api\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadLimitsTest extends TestCase
{
    #[DataProvider('provideShorthandValues')]
    public function testFromShorthandValue(string $shorthand, int $expectedBytes): void
    {
        $this->assertSame($expectedBytes, UploadLimits::fromShorthandValue($shorthand)->getMaxPostSizeInBytes());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideShorthandValues(): iterable
    {
        yield 'megabytes' => ['48M', 50331648];
        yield 'lowercase megabytes' => ['48m', 50331648];
        yield 'kilobytes' => ['512K', 524288];
        yield 'gigabytes' => ['1G', 1073741824];
        yield 'plain bytes' => ['1024', 1024];
        yield 'empty' => ['', 0];
        yield 'whitespace' => ['  ', 0];
    }

    public function testFromIni(): void
    {
        $this->assertGreaterThan(0, UploadLimits::fromIni()->getMaxPostSizeInBytes());
    }
}

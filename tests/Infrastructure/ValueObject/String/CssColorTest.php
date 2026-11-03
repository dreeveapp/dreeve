<?php

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\ValueObject\String\CssColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CssColorTest extends TestCase
{
    #[DataProvider('provideValidCssColors')]
    public function testShouldBeValid(string $color): void
    {
        $this->assertEquals(
            $color,
            (string) CssColor::fromString($color)
        );
    }

    public function testItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('lol is not a valid CSS color'));

        CssColor::fromString('LOL');
    }

    public static function provideValidCssColors(): iterable
    {
        yield 'named color' => ['red'];
        yield 'hex' => ['#000'];
        yield 'hex long' => ['#000111'];
        yield 'rgb()' => ['rgb(0,0,0)'];
        yield 'hsl()' => ['hsl(147, 50%, 47%)'];
    }
}

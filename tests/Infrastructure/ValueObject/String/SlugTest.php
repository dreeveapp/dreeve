<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\ValueObject\String\Slug;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    public function testItShouldSlugify(): void
    {
        self::assertEquals('morning-ride', (string) Slug::fromString('Morning Ride'));
        self::assertEquals('morning-ride', (string) Slug::fromString('  Morning   Ride  '));
        self::assertEquals('morning-ride', (string) Slug::fromString('Morning Ride! 🚴'));
        self::assertEquals('roc-d-azur-2024', (string) Slug::fromString("Roc d'Azur 2024"));
        self::assertEquals('hello-world', (string) Slug::fromString('hello_world'));
        self::assertEquals('123numbers456', (string) Slug::fromString('123numbers456'));
    }

    public function testItShouldSlugifyToEmptyStringWhenOnlySymbols(): void
    {
        self::assertEquals('', (string) Slug::fromString('🚴🚴'));
    }

    public function testItShouldThrowWhenEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromString('');
    }
}

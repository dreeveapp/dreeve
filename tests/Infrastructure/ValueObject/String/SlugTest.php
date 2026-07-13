<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Infrastructure\ValueObject\String\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    #[DataProvider(methodName: 'provideData')]
    public function testItShouldSlugify(string $input, string $expected): void
    {
        self::assertEquals($expected, (string) Slug::fromString($input));
    }

    public function testItShouldThrowWhenEmpty(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('App\\Infrastructure\\ValueObject\\String\\Slug can not be empty'));

        Slug::fromString('');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideData(): array
    {
        return [
            'simple' => ['Morning Ride', 'morning-ride'],
            'surrounding whitespace' => ['  Morning   Ride  ', 'morning-ride'],
            'special characters and emoji' => ['Morning Ride! 🚴', 'morning-ride'],
            'apostrophe' => ["Roc d'Azur 2024", 'roc-d-azur-2024'],
            'underscores' => ['hello_world', 'hello-world'],
            'numbers' => ['123numbers456', '123numbers456'],
            'only symbols' => ['🚴🚴', ''],
        ];
    }
}

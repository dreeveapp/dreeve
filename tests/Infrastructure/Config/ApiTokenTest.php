<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config;

use App\Infrastructure\Config\ApiToken;
use PHPUnit\Framework\TestCase;

class ApiTokenTest extends TestCase
{
    private const string VALID_TOKEN = 'a-token-that-is-long-enough-to-be-accepted';

    public function testItMatchesTheConfiguredToken(): void
    {
        $this->assertTrue(ApiToken::fromString(self::VALID_TOKEN)->matches(self::VALID_TOKEN));
    }

    public function testItDoesNotMatchAnotherToken(): void
    {
        $this->assertFalse(ApiToken::fromString(self::VALID_TOKEN)->matches('something-else-entirely-but-long-enough'));
    }

    public function testAnEmptyTokenDisablesTheApi(): void
    {
        $this->assertFalse(ApiToken::fromString('')->isEnabled());
    }

    public function testADisabledTokenMatchesNothing(): void
    {
        // Not even the empty string, otherwise an unconfigured install would
        // accept a request that sends no token at all.
        $this->assertFalse(ApiToken::fromString('')->matches(''));
    }

    public function testItIgnoresSurroundingWhitespace(): void
    {
        $this->assertTrue(ApiToken::fromString('  '.self::VALID_TOKEN.'  ')->matches(self::VALID_TOKEN));
    }

    public function testWhitespaceOnlyCountsAsEmpty(): void
    {
        $this->assertFalse(ApiToken::fromString("   \n ")->isEnabled());
    }

    public function testItRejectsATokenThatIsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API_TOKEN must be at least 32 characters long.');

        ApiToken::fromString('too-short');
    }

    public function testItAcceptsATokenOfExactlyTheMinimumLength(): void
    {
        $this->assertTrue(ApiToken::fromString(str_repeat('a', 32))->isEnabled());
    }
}

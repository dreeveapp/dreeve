<?php

declare(strict_types=1);

namespace App\Tests\Domain\Api;

use App\Domain\Api\Token;
use App\Domain\Api\TokenHash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenHashTest extends TestCase
{
    public function testMatches(): void
    {
        $token = Token::generate();

        $this->assertTrue(TokenHash::fromToken($token)->matches((string) $token));
    }

    #[DataProvider('provideTokensThatDoNotMatch')]
    public function testMatchesFails(string $token): void
    {
        $this->assertFalse(TokenHash::fromToken(Token::generate())->matches($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTokensThatDoNotMatch(): iterable
    {
        yield 'another token' => [(string) Token::generate()];
        yield 'an empty string' => [''];
        yield 'the prefix on its own' => ['drv_'];
        yield 'a sha256 of nothing' => [hash('sha256', '')];
    }

    #[DataProvider('provideInvalidHashes')]
    public function testFromStringThrows(string $hash): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Invalid API token hash'));

        TokenHash::fromString($hash);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidHashes(): iterable
    {
        // A hash that can never be constructed is a hash that can never accidentally authenticate.
        yield 'an empty hash' => [''];
        yield 'not hexadecimal' => [str_repeat('z', 64)];
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'uppercase' => [str_repeat('A', 64)];
    }

    public function testFromStringRoundTrip(): void
    {
        $token = Token::generate();

        $this->assertTrue(TokenHash::fromString((string) $token->hash())->matches((string) $token));
    }
}

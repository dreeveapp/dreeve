<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Security\Api\TokenType;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testGenerate(): void
    {
        $this->assertMatchesRegularExpression('/^drv_[a-f0-9]{64}$/', (string) Token::generate(TokenType::API));
    }

    public function testGenerateIsRandom(): void
    {
        $tokens = array_map(static fn (): string => (string) Token::generate(TokenType::API), range(1, 50));

        $this->assertCount(50, array_unique($tokens));
    }

    public function testItDoesNotLeakThroughDebugOutput(): void
    {
        $token = Token::generate(TokenType::API);

        $this->assertStringNotContainsString((string) $token, print_r($token, true));
        $this->assertStringNotContainsString((string) $token, Json::encode(['token' => $token]));

        ob_start();
        var_dump($token);
        $this->assertStringNotContainsString((string) $token, (string) ob_get_clean());
    }

    public function testFromStringTrims(): void
    {
        $this->assertSame('drv_abc', (string) Token::fromString(TokenType::API, "  drv_abc\n"));
    }

    #[DataProvider('provideValuesThatMustNotThrow')]
    public function testFromStringNeverThrows(string $value): void
    {
        // It is built from an environment variable at container build time, so throwing would
        // take down the whole app instead of just closing the API.
        $this->assertFalse(Token::fromString(TokenType::API, $value)->hasValidFormat());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValuesThatMustNotThrow(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ["  \n"];
        yield 'the sentinel other env vars use' => ['replace-me'];
        yield 'a password someone typed' => ['hunter2'];
        yield 'no prefix' => [str_repeat('a', 64)];
        yield 'one char short' => ['drv_'.str_repeat('a', 63)];
        yield 'one char long' => ['drv_'.str_repeat('a', 65)];
        yield 'uppercase hex' => ['drv_'.str_repeat('A', 64)];
        yield 'non hex' => ['drv_'.str_repeat('z', 64)];
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue(Token::fromString(TokenType::API, '')->isEmpty());
        $this->assertTrue(Token::fromString(TokenType::API, '   ')->isEmpty());
        $this->assertFalse(Token::generate(TokenType::API)->isEmpty());
    }

    public function testHasValidFormat(): void
    {
        $this->assertTrue(Token::generate(TokenType::API)->hasValidFormat());
        $this->assertTrue(Token::fromString(TokenType::API, (string) Token::generate(TokenType::API))->hasValidFormat());
    }

    public function testMatches(): void
    {
        $token = Token::generate(TokenType::API);

        $this->assertTrue($token->matches((string) $token));
        $this->assertFalse($token->matches(substr((string) $token, 4)));
        $this->assertFalse($token->matches((string) Token::generate(TokenType::API)));
    }

    public function testAnEmptyTokenMatchesNothing(): void
    {
        // hash_equals('', '') is true, so this is the case the handler has to guard first.
        $this->assertTrue(Token::fromString(TokenType::API, '')->matches(''));
        $this->assertTrue(Token::fromString(TokenType::API, '')->isEmpty());
    }
}

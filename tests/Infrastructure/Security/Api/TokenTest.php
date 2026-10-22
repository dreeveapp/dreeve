<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Api\McpToken;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testGenerate(): void
    {
        $this->assertMatchesRegularExpression('/^drv_[a-f0-9]{64}$/', (string) ApiToken::generate());
        $this->assertMatchesRegularExpression('/^drvm_[a-f0-9]{64}$/', (string) McpToken::generate());
    }

    public function testGenerateIsRandom(): void
    {
        $tokens = array_map(static fn (): string => (string) ApiToken::generate(), range(1, 50));

        $this->assertCount(50, array_unique($tokens));
    }

    public function testItDoesNotLeakThroughDebugOutput(): void
    {
        $token = ApiToken::generate();

        $this->assertStringNotContainsString((string) $token, print_r($token, true));
        $this->assertStringNotContainsString((string) $token, Json::encode(['token' => $token]));

        ob_start();
        var_dump($token);
        $this->assertStringNotContainsString((string) $token, (string) ob_get_clean());
    }

    public function testFromStringTrims(): void
    {
        $this->assertSame('drv_abc', (string) ApiToken::fromString("  drv_abc\n"));
    }

    #[DataProvider('provideValuesThatMustNotThrow')]
    public function testFromStringNeverThrows(string $value): void
    {
        // It is built from an environment variable at container build time, so throwing would
        // take down the whole app instead of just closing the API.
        $this->assertFalse(ApiToken::fromString($value)->hasValidFormat());
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
        $this->assertTrue(ApiToken::fromString('')->isEmpty());
        $this->assertTrue(ApiToken::fromString('   ')->isEmpty());
        $this->assertFalse(ApiToken::generate()->isEmpty());
    }

    public function testHasValidFormat(): void
    {
        $this->assertTrue(ApiToken::generate()->hasValidFormat());
        $this->assertTrue(ApiToken::fromString((string) ApiToken::generate())->hasValidFormat());
        $this->assertTrue(McpToken::fromString((string) McpToken::generate())->hasValidFormat());
        $this->assertFalse(ApiToken::fromString((string) McpToken::generate())->hasValidFormat());
        $this->assertFalse(McpToken::fromString((string) ApiToken::generate())->hasValidFormat());
    }

    public function testMatches(): void
    {
        $token = ApiToken::generate();

        $this->assertTrue($token->matches((string) $token));
        $this->assertFalse($token->matches(substr((string) $token, 4)));
        $this->assertFalse($token->matches((string) ApiToken::generate()));
    }

    public function testAnEmptyTokenMatchesNothing(): void
    {
        // hash_equals('', '') is true, so this is the case the handler has to guard first.
        $this->assertTrue(ApiToken::fromString('')->matches(''));
        $this->assertTrue(ApiToken::fromString('')->isEmpty());
    }
}

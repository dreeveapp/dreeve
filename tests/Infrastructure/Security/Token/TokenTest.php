<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Token;

use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Security\Token\McpToken;
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

    public function testFromStringSplitsAndTrims(): void
    {
        $this->assertSame('drv_abc', (string) ApiToken::fromString("  drv_abc\n"));
        $this->assertSame('drv_abc,drv_def', (string) ApiToken::fromString(" drv_abc , drv_def\n"));
        $this->assertSame('drv_abc,drv_def', (string) ApiToken::fromString('drv_abc,, ,drv_def,'));

        $this->assertCount(0, ApiToken::fromString(' , ,'));
        $this->assertCount(1, ApiToken::generate());
        $this->assertCount(2, ApiToken::fromString('drv_abc,, ,drv_def,'));
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
        yield 'only commas' => [' , ,'];
        yield 'one malformed entry in a list' => ['drv_'.str_repeat('a', 64).',hunter2'];
        yield 'one key with the wrong prefix in a list' => ['drv_'.str_repeat('a', 64).',drvm_'.str_repeat('a', 64)];
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue(ApiToken::fromString('')->isEmpty());
        $this->assertTrue(ApiToken::fromString('   ')->isEmpty());
        $this->assertTrue(ApiToken::fromString(' , ,')->isEmpty());
        $this->assertFalse(ApiToken::generate()->isEmpty());
    }

    public function testHasValidFormat(): void
    {
        $this->assertTrue(ApiToken::generate()->hasValidFormat());
        $this->assertTrue(ApiToken::fromString((string) ApiToken::generate())->hasValidFormat());
        $this->assertTrue(McpToken::fromString((string) McpToken::generate())->hasValidFormat());
        $this->assertFalse(ApiToken::fromString((string) McpToken::generate())->hasValidFormat());
        $this->assertFalse(McpToken::fromString((string) ApiToken::generate())->hasValidFormat());
        $this->assertTrue(ApiToken::fromString(ApiToken::generate().', '.ApiToken::generate().',')->hasValidFormat());
    }

    public function testMatches(): void
    {
        $token = ApiToken::generate();

        $this->assertTrue($token->matches((string) $token));
        $this->assertFalse($token->matches(substr((string) $token, 4)));
        $this->assertFalse($token->matches((string) ApiToken::generate()));

        $first = ApiToken::generate();
        $second = ApiToken::generate();
        $tokens = ApiToken::fromString($first.','.$second);

        $this->assertTrue($tokens->matches((string) $first));
        $this->assertTrue($tokens->matches((string) $second));
        $this->assertFalse($tokens->matches($first.','.$second));
        $this->assertFalse($tokens->matches(''));
    }

    public function testAnEmptyTokenMatchesNothing(): void
    {
        $this->assertFalse(ApiToken::fromString('')->matches(''));
        $this->assertTrue(ApiToken::fromString('')->isEmpty());
    }
}

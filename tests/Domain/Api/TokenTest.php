<?php

declare(strict_types=1);

namespace App\Tests\Domain\Api;

use App\Domain\Api\Token;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testGenerate(): void
    {
        $this->assertMatchesRegularExpression('/^drv_[a-f0-9]{64}$/', (string) Token::generate());
    }

    public function testGenerateIsRandom(): void
    {
        $tokens = array_map(static fn (): string => (string) Token::generate(), range(1, 50));

        $this->assertCount(50, array_unique($tokens));
    }

    public function testItDoesNotLeakThroughDebugOutput(): void
    {
        $token = Token::generate();

        // Every route a secret usually escapes through: a dumped stack trace, a logged context,
        // an encoded aggregate. var_export() is the one exception, it reads the real properties
        // and PHP offers no hook to intercept it.
        $this->assertStringNotContainsString((string) $token, print_r($token, true));
        $this->assertStringNotContainsString((string) $token, Json::encode(['token' => $token]));

        ob_start();
        var_dump($token);
        $this->assertStringNotContainsString((string) $token, (string) ob_get_clean());
    }

    public function testHashCoversThePrefixToo(): void
    {
        $token = Token::generate();

        $this->assertSame(hash('sha256', (string) $token), (string) $token->hash());
        $this->assertTrue($token->hash()->matches((string) $token));
        $this->assertFalse($token->hash()->matches(substr((string) $token, 4)));
    }
}

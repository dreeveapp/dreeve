<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Infrastructure\Security\Api\ApiTokenHandler;
use App\Infrastructure\Security\Api\ApiUser;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Security\Api\TokenType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiTokenHandlerTest extends TestCase
{
    public function testItResolvesTheApiUserForTheConfiguredToken(): void
    {
        $token = Token::generate(TokenType::API);

        $userBadge = new ApiTokenHandler($token)->getUserBadgeFrom((string) $token);

        $this->assertSame(TokenType::API->getUserIdentifier(), $userBadge->getUserIdentifier());
    }

    public function testItSuppliesItsOwnUserLoader(): void
    {
        $token = Token::generate(TokenType::API);

        // Without a loader the authenticator falls back to the user provider.
        $userLoader = new ApiTokenHandler($token)->getUserBadgeFrom((string) $token)->getUserLoader();

        $this->assertNotNull($userLoader);
        $this->assertEquals(new ApiUser(TokenType::API), $userLoader(TokenType::API->getUserIdentifier()));
    }

    public function testItRejectsAnotherToken(): void
    {
        $handler = new ApiTokenHandler(Token::generate(TokenType::API));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom((string) Token::generate(TokenType::API));
    }

    public function testItRejectsTheTokenWithoutItsPrefix(): void
    {
        $token = Token::generate(TokenType::API);
        $handler = new ApiTokenHandler($token);

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom(str_replace('drv_', '', (string) $token));
    }

    public function testItRejectsAnEmptyToken(): void
    {
        $handler = new ApiTokenHandler(Token::generate(TokenType::API));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom('');
    }

    #[DataProvider('provideValuesThatCloseTheApi')]
    public function testItRejectsEveryTokenWhenTheKeyIsNotUsable(string $configured, string $sent): void
    {
        $handler = new ApiTokenHandler(Token::fromString(TokenType::API, $configured));

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom($sent);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValuesThatCloseTheApi(): iterable
    {
        // hash_equals('', '') is true, so an unconfigured instance must reject an empty bearer
        // token rather than hand it ROLE_API.
        yield 'not configured, empty token sent' => ['', ''];
        yield 'not configured, real token sent' => ['', (string) Token::generate(TokenType::API)];
        yield 'whitespace only' => ['   ', ''];
        // Copying the sentinel the Strava vars use is the mistake most likely to be made.
        yield 'the replace-me sentinel' => ['replace-me', 'replace-me'];
        yield 'a password someone typed' => ['hunter2', 'hunter2'];
        yield 'right length, no prefix' => [str_repeat('a', 64), str_repeat('a', 64)];
        yield 'uppercase hex' => ['drv_'.str_repeat('A', 64), 'drv_'.str_repeat('A', 64)];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Api\ApiTokenHandler;
use App\Infrastructure\Security\Api\ApiUser;
use App\Infrastructure\Security\Api\McpToken;
use App\Infrastructure\Security\Api\McpTokenHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiTokenHandlerTest extends TestCase
{
    public function testItResolvesTheApiUserForTheConfiguredToken(): void
    {
        $token = ApiToken::generate();

        $userBadge = new ApiTokenHandler($token)->getUserBadgeFrom((string) $token);

        $this->assertSame('dreeve-api', $userBadge->getUserIdentifier());

        $token = McpToken::generate();
        $userBadge = new McpTokenHandler($token)->getUserBadgeFrom((string) $token);

        $this->assertSame('dreeve-mcp', $userBadge->getUserIdentifier());
        $this->assertSame(['ROLE_MCP'], $userBadge->getUser()->getRoles());
    }

    public function testItSuppliesItsOwnUserLoader(): void
    {
        $token = ApiToken::generate();

        // Without a loader the authenticator falls back to the user provider.
        $userLoader = new ApiTokenHandler($token)->getUserBadgeFrom((string) $token)->getUserLoader();

        $this->assertNotNull($userLoader);
        $this->assertEquals(new ApiUser('dreeve-api', 'ROLE_API'), $userLoader('dreeve-api'));
    }

    public function testItRejectsAnotherToken(): void
    {
        $handler = new ApiTokenHandler(ApiToken::generate());

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom((string) ApiToken::generate());
    }

    public function testItRejectsTheTokenWithoutItsPrefix(): void
    {
        $token = ApiToken::generate();
        $handler = new ApiTokenHandler($token);

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom(str_replace('drv_', '', (string) $token));
    }

    public function testItRejectsAnEmptyToken(): void
    {
        $handler = new ApiTokenHandler(ApiToken::generate());

        $this->expectException(BadCredentialsException::class);

        $handler->getUserBadgeFrom('');
    }

    #[DataProvider('provideValuesThatCloseTheApi')]
    public function testItRejectsEveryTokenWhenTheKeyIsNotUsable(string $configured, string $sent): void
    {
        $handler = new ApiTokenHandler(ApiToken::fromString($configured));

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
        yield 'not configured, real token sent' => ['', (string) ApiToken::generate()];
        yield 'whitespace only' => ['   ', ''];
        // Copying the sentinel the Strava vars use is the mistake most likely to be made.
        yield 'the replace-me sentinel' => ['replace-me', 'replace-me'];
        yield 'a password someone typed' => ['hunter2', 'hunter2'];
        yield 'right length, no prefix' => [str_repeat('a', 64), str_repeat('a', 64)];
        yield 'uppercase hex' => ['drv_'.str_repeat('A', 64), 'drv_'.str_repeat('A', 64)];
    }
}

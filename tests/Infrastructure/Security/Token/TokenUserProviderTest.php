<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Token;

use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Security\Token\McpToken;
use App\Infrastructure\Security\Token\TokenUser;
use App\Infrastructure\Security\Token\TokenUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class TokenUserProviderTest extends TestCase
{
    private TokenUserProvider $tokenUserProvider;

    public function testLoadUserByIdentifier(): void
    {
        $user = $this->tokenUserProvider->loadUserByIdentifier('dreeve-api');

        $this->assertSame('dreeve-api', $user->getUserIdentifier());
        $this->assertSame(['ROLE_API'], $user->getRoles());

        $user = $this->tokenUserProvider->loadUserByIdentifier('dreeve-mcp');

        $this->assertSame('dreeve-mcp', $user->getUserIdentifier());
        $this->assertSame(['ROLE_MCP'], $user->getRoles());
    }

    public function testLoadUserByIdentifierForAnotherIdentifier(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->tokenUserProvider->loadUserByIdentifier('admin');
    }

    public function testRefreshUser(): void
    {
        $this->assertEquals(new TokenUser('dreeve-api', 'ROLE_API'), $this->tokenUserProvider->refreshUser(new TokenUser('dreeve-api', 'ROLE_API')));
    }

    public function testRefreshUserForAnotherUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->tokenUserProvider->refreshUser(new InMemoryUser('admin', 'hash', ['ROLE_ADMIN']));
    }

    public function testSupportsClass(): void
    {
        $this->assertTrue($this->tokenUserProvider->supportsClass(TokenUser::class));
        $this->assertFalse($this->tokenUserProvider->supportsClass(InMemoryUser::class));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenUserProvider = new TokenUserProvider(ApiToken::generate(), McpToken::generate());
    }
}

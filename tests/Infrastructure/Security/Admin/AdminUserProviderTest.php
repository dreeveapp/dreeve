<?php

namespace App\Tests\Infrastructure\Security\Admin;

use App\Infrastructure\Security\Admin\AdminPasswordHash;
use App\Infrastructure\Security\Admin\AdminUserName;
use App\Infrastructure\Security\Admin\AdminUserProvider;
use App\Infrastructure\Security\Oidc\OidcConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifier(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $user = $provider->loadUserByIdentifier('admin');

        $this->assertInstanceOf(InMemoryUser::class, $user);
        $this->assertEquals('admin', $user->getUserIdentifier());
        $this->assertEquals('hashed-password', $user->getPassword());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testLoadUserByIdentifierItShouldThrowWhenPasswordHashIsEmpty(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString(''),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $this->expectExceptionObject(new UserNotFoundException(''));

        $provider->loadUserByIdentifier('admin');
    }

    public function testLoadUserByIdentifierWithoutPasswordHashWhenOidcIsEnabled(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString(''),
            OidcConfig::fromStrings('https://idp.example.com', 'dreeve', 'secret', 'athlete@example.com'),
        );

        $user = $provider->loadUserByIdentifier('admin');

        $this->assertEquals('admin', $user->getUserIdentifier());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testLoadUserByIdentifierItShouldThrowWhenIdentifierDoesNotMatch(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $this->expectExceptionObject(new UserNotFoundException(''));

        $provider->loadUserByIdentifier('someone-else');
    }

    public function testRefreshUserAndSupportsClass(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $user = $provider->refreshUser(new InMemoryUser('admin', 'hashed-password', ['ROLE_ADMIN']));

        $this->assertInstanceOf(InMemoryUser::class, $user);
        $this->assertEquals('admin', $user->getUserIdentifier());
        $this->assertEquals('hashed-password', $user->getPassword());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());

        $this->assertTrue($provider->supportsClass(InMemoryUser::class));
        $this->assertFalse($provider->supportsClass(UserInterface::class));
    }

    public function testRefreshUserItShouldThrowWhenUserIsNotSupported(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
            OidcConfig::fromStrings('', '', '', ''),
        );

        $this->expectExceptionObject(new UnsupportedUserException(''));

        $provider->refreshUser($this->createStub(UserInterface::class));
    }
}

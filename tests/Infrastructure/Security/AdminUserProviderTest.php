<?php

namespace App\Tests\Infrastructure\Security;

use App\Infrastructure\Security\AdminPasswordHash;
use App\Infrastructure\Security\AdminUserName;
use App\Infrastructure\Security\AdminUserProvider;
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
        );

        $this->expectExceptionObject(new UserNotFoundException(''));

        $provider->loadUserByIdentifier('admin');
    }

    public function testLoadUserByIdentifierItShouldThrowWhenIdentifierDoesNotMatch(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
        );

        $this->expectExceptionObject(new UserNotFoundException(''));

        $provider->loadUserByIdentifier('someone-else');
    }

    public function testRefreshUser(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
        );

        $user = $provider->refreshUser(new InMemoryUser('admin', 'hashed-password', ['ROLE_ADMIN']));

        $this->assertInstanceOf(InMemoryUser::class, $user);
        $this->assertEquals('admin', $user->getUserIdentifier());
        $this->assertEquals('hashed-password', $user->getPassword());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function testRefreshUserItShouldThrowWhenUserIsNotSupported(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
        );

        $this->expectExceptionObject(new UnsupportedUserException(''));

        $provider->refreshUser($this->createStub(UserInterface::class));
    }

    public function testSupportsClass(): void
    {
        $provider = new AdminUserProvider(
            AdminUserName::fromString('admin'),
            AdminPasswordHash::fromString('hashed-password'),
        );

        $this->assertTrue($provider->supportsClass(InMemoryUser::class));
        $this->assertFalse($provider->supportsClass(UserInterface::class));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Api;

use App\Infrastructure\Security\Api\ApiUser;
use App\Infrastructure\Security\Api\ApiUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ApiUserProviderTest extends TestCase
{
    private ApiUserProvider $apiUserProvider;

    public function testLoadUserByIdentifier(): void
    {
        $user = $this->apiUserProvider->loadUserByIdentifier(ApiUser::IDENTIFIER);

        $this->assertSame(ApiUser::IDENTIFIER, $user->getUserIdentifier());
        $this->assertSame(['ROLE_API'], $user->getRoles());
    }

    public function testLoadUserByIdentifierForAnotherIdentifier(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->apiUserProvider->loadUserByIdentifier('admin');
    }

    public function testRefreshUser(): void
    {
        $this->assertEquals(new ApiUser(), $this->apiUserProvider->refreshUser(new ApiUser()));
    }

    public function testRefreshUserForAnotherUser(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->apiUserProvider->refreshUser(new InMemoryUser('admin', 'hash', ['ROLE_ADMIN']));
    }

    public function testSupportsClass(): void
    {
        $this->assertTrue($this->apiUserProvider->supportsClass(ApiUser::class));
        $this->assertFalse($this->apiUserProvider->supportsClass(InMemoryUser::class));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->apiUserProvider = new ApiUserProvider();
    }
}

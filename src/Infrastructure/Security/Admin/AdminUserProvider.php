<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Admin;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<InMemoryUser>
 */
final readonly class AdminUserProvider implements UserProviderInterface
{
    public function __construct(
        private AdminUserName $adminUsername,
        private AdminPasswordHash $adminPasswordHash,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($this->adminPasswordHash->isEmpty() || $identifier !== (string) $this->adminUsername) {
            throw new UserNotFoundException();
        }

        return new InMemoryUser(
            username: (string) $this->adminUsername,
            password: (string) $this->adminPasswordHash,
            roles: ['ROLE_ADMIN'],
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return InMemoryUser::class === $class;
    }
}

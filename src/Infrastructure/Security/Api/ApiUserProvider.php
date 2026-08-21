<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<ApiUser>
 */
final readonly class ApiUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (ApiUser::IDENTIFIER !== $identifier) {
            throw new UserNotFoundException();
        }

        return new ApiUser();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiUser) {
            throw new UnsupportedUserException();
        }

        return new ApiUser();
    }

    public function supportsClass(string $class): bool
    {
        return ApiUser::class === $class;
    }
}

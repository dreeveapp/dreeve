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
    public function __construct(
        private ApiToken $apiToken,
        private McpToken $mcpToken,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        foreach ([$this->apiToken, $this->mcpToken] as $token) {
            if ($token->getUserIdentifier() === $identifier) {
                return ApiUser::fromToken($token);
            }
        }

        throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiUser) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return ApiUser::class === $class;
    }
}

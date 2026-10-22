<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Mcp\McpToken;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<TokenUser>
 */
final readonly class TokenUserProvider implements UserProviderInterface
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
                return TokenUser::fromToken($token);
            }
        }

        throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof TokenUser) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return TokenUser::class === $class;
    }
}

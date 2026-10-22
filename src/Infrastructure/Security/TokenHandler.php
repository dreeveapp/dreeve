<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Security\Api\ApiToken;
use App\Infrastructure\Security\Mcp\McpToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class TokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiToken $apiToken,
        private McpToken $mcpToken,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        foreach ([$this->apiToken, $this->mcpToken] as $token) {
            if ($token->isEmpty() || !$token->hasValidFormat() || !$token->matches($accessToken)) {
                continue;
            }

            $user = TokenUser::fromToken($token);

            return new UserBadge($user->getUserIdentifier(), static fn (): TokenUser => $user);
        }

        throw new BadCredentialsException();
    }
}

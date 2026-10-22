<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiToken $token,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        if ($this->token->isEmpty() || !$this->token->hasValidFormat() || !$this->token->matches($accessToken)) {
            throw new BadCredentialsException();
        }

        $user = ApiUser::fromToken($this->token);

        return new UserBadge($user->getUserIdentifier(), static fn (): ApiUser => $user);
    }
}

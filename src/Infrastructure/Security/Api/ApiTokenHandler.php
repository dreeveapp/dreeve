<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use App\Domain\Api\TokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private TokenRepository $tokenRepository,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $storedToken = $this->tokenRepository->find();

        if (!$storedToken?->getHash()->matches($accessToken)) {
            throw new BadCredentialsException();
        }

        return new UserBadge(ApiUser::IDENTIFIER, static fn (): ApiUser => new ApiUser());
    }
}

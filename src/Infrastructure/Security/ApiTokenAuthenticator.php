<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Config\ApiToken;
use App\Infrastructure\Http\HttpStatusCode;
use App\Infrastructure\Http\JsonErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public const string ROLE = 'ROLE_API';
    private const string USER_IDENTIFIER = 'api';

    public function __construct(
        private readonly ApiToken $apiToken,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        // The firewall pattern already scopes this authenticator to the API.
        return true;
    }

    #[\Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        if (!$this->apiToken->isEnabled()) {
            throw new CustomUserMessageAuthenticationException('The API is disabled. Set API_TOKEN to enable it.');
        }

        if (null === $token = $this->extractToken($request)) {
            throw new CustomUserMessageAuthenticationException('Missing or malformed Authorization header, expected "Authorization: Bearer <token>".');
        }

        if (!$this->apiToken->matches($token)) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                self::USER_IDENTIFIER,
                fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null, [self::ROLE]),
            )
        );
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header)) {
            return null;
        }

        if (1 !== preg_match('/^Bearer\s+(?<token>\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches['token'];
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonErrorResponse
    {
        return new JsonErrorResponse(
            ['message' => $exception->getMessage()],
            HttpStatusCode::UNAUTHORIZED->value,
        );
    }
}

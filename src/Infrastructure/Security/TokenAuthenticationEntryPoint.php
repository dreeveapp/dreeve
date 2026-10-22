<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Http\Api\ApiErrorCode;
use App\Infrastructure\Http\Api\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final readonly class TokenAuthenticationEntryPoint implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    private const string REALM = 'Dreeve';

    public function start(Request $request, ?AuthenticationException $authException = null): ApiErrorResponse
    {
        return $this->unauthorized();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ApiErrorResponse
    {
        return $this->unauthorized();
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: Response::HTTP_FORBIDDEN,
            error: ApiErrorCode::INSUFFICIENT_SCOPE,
            message: 'This token does not grant access to this endpoint.',
            headers: ['WWW-Authenticate' => sprintf('Bearer realm="%s", error="insufficient_scope"', self::REALM)],
        );
    }

    private function unauthorized(): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: Response::HTTP_UNAUTHORIZED,
            error: ApiErrorCode::INVALID_TOKEN,
            message: 'A valid token is required. Send it in the Authorization header as a Bearer token.',
            headers: ['WWW-Authenticate' => sprintf('Bearer realm="%s"', self::REALM)],
        );
    }
}

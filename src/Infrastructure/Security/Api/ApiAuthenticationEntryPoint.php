<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

use App\Infrastructure\Http\Api\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final readonly class ApiAuthenticationEntryPoint implements AuthenticationEntryPointInterface, AuthenticationFailureHandlerInterface
{
    private const string REALM = 'Dreeve';

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->unauthorized();
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->unauthorized();
    }

    private function unauthorized(): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: Response::HTTP_UNAUTHORIZED,
            error: 'invalid_token',
            message: 'A valid API token is required. Send it in the Authorization header as a Bearer token.',
            headers: ['WWW-Authenticate' => sprintf('Bearer realm="%s"', self::REALM)],
        );
    }
}

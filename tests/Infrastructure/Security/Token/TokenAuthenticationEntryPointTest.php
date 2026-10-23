<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security\Token;

use App\Infrastructure\Security\Token\TokenAuthenticationEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class TokenAuthenticationEntryPointTest extends TestCase
{
    private TokenAuthenticationEntryPoint $tokenAuthenticationEntryPoint;

    public function testStart(): void
    {
        $response = $this->tokenAuthenticationEntryPoint->start(Request::create('/api/v1/status'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame('Bearer realm="Dreeve"', $response->headers->get('WWW-Authenticate'));
        $this->assertSame(
            '{"error":"invalid_token","message":"A valid token is required. Send it in the Authorization header as a Bearer token."}',
            $response->getContent(),
        );
    }

    public function testOnAuthenticationFailureAnswersTheSameWayAsStart(): void
    {
        $failure = $this->tokenAuthenticationEntryPoint->onAuthenticationFailure(
            Request::create('/api/v1/status'),
            new BadCredentialsException(),
        );

        $this->assertEquals(
            $this->tokenAuthenticationEntryPoint->start(Request::create('/api/v1/status')),
            $failure,
        );
    }

    public function testItDoesNotLeakTheAuthenticationException(): void
    {
        $response = $this->tokenAuthenticationEntryPoint->onAuthenticationFailure(
            Request::create('/api/v1/status'),
            new BadCredentialsException('drv_the-token-that-was-tried'),
        );

        $this->assertStringNotContainsString('drv_', (string) $response->getContent());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenAuthenticationEntryPoint = new TokenAuthenticationEntryPoint();
    }
}

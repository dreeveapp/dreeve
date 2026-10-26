<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

final readonly class OidcLoginAttempt
{
    public const string SESSION_KEY = 'oidc_login_attempt';

    private function __construct(
        private string $state,
        private string $nonce,
        private string $codeVerifier,
    ) {
    }

    public static function start(): self
    {
        return new self(
            state: self::randomString(),
            nonce: self::randomString(),
            codeVerifier: self::randomString(),
        );
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }

    public function getCodeVerifier(): string
    {
        return $this->codeVerifier;
    }

    public function getCodeChallenge(): string
    {
        return self::base64UrlEncode(hash('sha256', $this->codeVerifier, true));
    }

    private static function randomString(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

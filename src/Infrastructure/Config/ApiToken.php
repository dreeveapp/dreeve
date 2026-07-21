<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final readonly class ApiToken
{
    /**
     * Anything shorter than this is trivially brute-forceable and is rejected at
     * boot rather than silently accepted.
     */
    private const int MINIMUM_LENGTH = 32;

    private function __construct(
        private string $token,
    ) {
    }

    public static function fromString(string $token): self
    {
        $token = trim($token);

        if ('' !== $token && strlen($token) < self::MINIMUM_LENGTH) {
            throw new \InvalidArgumentException(sprintf('API_TOKEN must be at least %d characters long.', self::MINIMUM_LENGTH));
        }

        return new self($token);
    }

    /**
     * An empty token disables the API entirely. This is the default, so an
     * installation that never opts in exposes no write surface.
     */
    public function isEnabled(): bool
    {
        return '' !== $this->token;
    }

    public function matches(string $candidate): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return hash_equals($this->token, $candidate);
    }
}

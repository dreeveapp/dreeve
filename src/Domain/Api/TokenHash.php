<?php

declare(strict_types=1);

namespace App\Domain\Api;

final readonly class TokenHash implements \Stringable
{
    private function __construct(
        private string $hash,
    ) {
    }

    public static function fromToken(Token $token): self
    {
        return new self(hash('sha256', (string) $token));
    }

    public static function fromString(string $hash): self
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new \InvalidArgumentException('Invalid API token hash');
        }

        return new self($hash);
    }

    public function matches(#[\SensitiveParameter] string $token): bool
    {
        return hash_equals($this->hash, hash('sha256', $token));
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}

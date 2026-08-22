<?php

declare(strict_types=1);

namespace App\Domain\Api;

final readonly class Token implements \Stringable
{
    private const string PREFIX = 'drv_';
    private const int LENGTH_IN_BYTES = 32;

    private function __construct(
        #[\SensitiveParameter]
        private string $token,
    ) {
    }

    public static function generate(): self
    {
        return new self(self::PREFIX.bin2hex(random_bytes(self::LENGTH_IN_BYTES)));
    }

    public static function fromString(#[\SensitiveParameter] string $token): self
    {
        return new self(trim($token));
    }

    public function isEmpty(): bool
    {
        return '' === $this->token;
    }

    public function hasValidFormat(): bool
    {
        return 1 === preg_match('/^'.self::PREFIX.'[a-f0-9]{'.(self::LENGTH_IN_BYTES * 2).'}$/', $this->token);
    }

    public function matches(#[\SensitiveParameter] string $token): bool
    {
        return hash_equals($this->token, $token);
    }

    public function __toString(): string
    {
        return $this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [];
    }
}

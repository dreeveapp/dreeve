<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Token;

abstract readonly class Token implements \Stringable
{
    private const int LENGTH_IN_BYTES = 32;

    final private function __construct(
        #[\SensitiveParameter]
        private string $token,
    ) {
    }

    abstract protected static function getPrefix(): string;

    abstract public function getRole(): string;

    /**
     * @return non-empty-string
     */
    abstract public function getUserIdentifier(): string;

    public static function generate(): static
    {
        return new static(static::getPrefix().bin2hex(random_bytes(self::LENGTH_IN_BYTES)));
    }

    public static function fromString(#[\SensitiveParameter] string $token): static
    {
        return new static(trim($token));
    }

    public function isEmpty(): bool
    {
        return '' === $this->token;
    }

    public function hasValidFormat(): bool
    {
        return 1 === preg_match('/^'.static::getPrefix().'[a-f0-9]{'.(self::LENGTH_IN_BYTES * 2).'}$/', $this->token);
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

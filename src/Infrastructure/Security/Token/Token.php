<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Token;

abstract readonly class Token implements \Stringable, \Countable
{
    private const int LENGTH_IN_BYTES = 32;

    /**
     * @param list<non-empty-string> $tokens
     */
    final private function __construct(
        #[\SensitiveParameter]
        private array $tokens,
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
        return new static([static::getPrefix().bin2hex(random_bytes(self::LENGTH_IN_BYTES))]);
    }

    public static function fromString(#[\SensitiveParameter] string $tokens): static
    {
        return new static(array_values(array_filter(
            array_map(trim(...), explode(',', $tokens)),
            static fn (string $token): bool => '' !== $token,
        )));
    }

    public function isEmpty(): bool
    {
        return [] === $this->tokens;
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function hasValidFormat(): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        return array_all($this->tokens, fn (string $token): bool => 1 === preg_match('/^'.static::getPrefix().'[a-f0-9]{'.(self::LENGTH_IN_BYTES * 2).'}$/', $token));
    }

    public function matches(
        #[\SensitiveParameter] string $token,
    ): bool {
        return array_any($this->tokens, fn (string $configuredToken): bool => hash_equals($configuredToken, $token));
    }

    public function __toString(): string
    {
        return implode(',', $this->tokens);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [];
    }
}

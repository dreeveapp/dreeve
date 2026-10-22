<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Api;

final readonly class Token implements \Stringable
{
    private const int LENGTH_IN_BYTES = 32;

    private function __construct(
        private TokenType $type,
        #[\SensitiveParameter]
        private string $token,
    ) {
    }

    public static function generate(TokenType $type): self
    {
        return new self($type, $type->getPrefix().bin2hex(random_bytes(self::LENGTH_IN_BYTES)));
    }

    public static function fromString(TokenType $type, #[\SensitiveParameter] string $token): self
    {
        return new self($type, trim($token));
    }

    public function getType(): TokenType
    {
        return $this->type;
    }

    public function isEmpty(): bool
    {
        return '' === $this->token;
    }

    public function hasValidFormat(): bool
    {
        return 1 === preg_match('/^'.$this->type->getPrefix().'[a-f0-9]{'.(self::LENGTH_IN_BYTES * 2).'}$/', $this->token);
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

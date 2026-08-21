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

    public function hash(): TokenHash
    {
        return TokenHash::fromToken($this);
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

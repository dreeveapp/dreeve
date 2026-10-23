<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Admin;

final readonly class AdminPasswordHash implements \Stringable
{
    private function __construct(
        private string $hash,
    ) {
    }

    public static function fromString(string $string): self
    {
        return new self(trim($string));
    }

    public function isEmpty(): bool
    {
        return '' === $this->hash;
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Tokenizer;

final readonly class Token
{
    private function __construct(
        private string $prefix,
        private string $key,
        private ?string $modifier,
        private string $raw,
    ) {
    }

    public static function create(
        string $prefix,
        string $key,
        ?string $modifier,
        string $raw,
    ): self {
        return new self(
            prefix: $prefix,
            key: $key,
            modifier: $modifier,
            raw: $raw,
        );
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getModifier(): ?string
    {
        return $this->modifier;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }
}

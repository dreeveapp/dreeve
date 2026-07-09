<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI;

final readonly class AIApiKey implements \Stringable
{
    public function __construct(
        private string $apiKey,
    ) {
    }

    public static function fromServerVar(): self
    {
        return new self(
            $_SERVER['AI_API_KEY'] ?? 'replace-me'
        );
    }

    public function isEmpty(): bool
    {
        return '' === $this->apiKey;
    }

    public function __toString(): string
    {
        return $this->apiKey;
    }
}

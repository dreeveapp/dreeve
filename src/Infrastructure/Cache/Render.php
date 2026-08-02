<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

final readonly class Render
{
    private function __construct(
        public ?string $content,
        public bool $wasServedFromCache,
    ) {
    }

    public static function servedFromCache(?string $content): self
    {
        return new self(
            content: $content,
            wasServedFromCache: true,
        );
    }

    public static function freshlyRendered(?string $content): self
    {
        return new self(
            content: $content,
            wasServedFromCache: false,
        );
    }
}

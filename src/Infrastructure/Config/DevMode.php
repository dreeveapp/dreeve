<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

final readonly class DevMode
{
    private function __construct(
        private bool $isEnabled,
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(filter_var($value, FILTER_VALIDATE_BOOL));
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }
}

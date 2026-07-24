<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Tokenizer;

final readonly class GearStub
{
    private function __construct(
        private string $name,
    ) {
    }

    public static function withName(string $name): self
    {
        return new self($name);
    }

    public function getName(): string
    {
        return $this->name;
    }
}

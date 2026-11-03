<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation;

use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class RunAnOperation extends DomainCommand
{
    private readonly string $notInitialized; // @phpstan-ignore property.uninitializedReadonly, property.unused

    public function __construct(
        private ?string $value,
        private ?string $valueTwo = 'defaultValue',
    ) {
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getValueTwo(): ?string
    {
        return $this->valueTwo;
    }
}

<?php

namespace App\Tests\Infrastructure\CQRS\Command\Bus\StoreAValueAndFailFromPayload;

use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class StoreAValueAndFailFromPayload extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    public static function fromPayload(array $payload): self
    {
        return new self();
    }
}

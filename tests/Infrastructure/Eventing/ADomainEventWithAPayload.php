<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Eventing;

use App\Infrastructure\Eventing\DomainEvent;

class ADomainEventWithAPayload extends DomainEvent
{
    public function __construct(
        private readonly string $payload,
    ) {
    }

    public function getPayload(): string
    {
        return $this->payload;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\Identifier;

use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use Ramsey\Uuid\Uuid;

class FakeUuidFactory implements UuidFactory
{
    private int $numberOfCalls = 0;

    public function random(): string
    {
        return Uuid::fromString(sprintf('0025176c-5652-11ee-923d-%012x', 0x02424DD627D5 + $this->numberOfCalls++))->toString();
    }
}

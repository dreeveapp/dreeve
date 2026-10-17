<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\DeleteAthleteWeight;

use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DeleteAthleteWeight extends DomainCommand
{
    private function __construct(
        private SerializableDateTime $on,
    ) {
    }

    public static function from(SerializableDateTime $on): self
    {
        return new self(on: $on);
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\ResetDashboardLayoutToDefault;

use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

#[RequiresRebuild]
final readonly class ResetDashboardLayoutToDefault extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct()
    {
    }

    public static function fromPayload(array $payload): self
    {
        return new self();
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command;

/**
 * Thrown by a command handler when a successfully deserialized command cannot be
 * processed because of invalid domain state/input. Surfaced to the client as a
 * 400 with the given reason.
 */
final class CouldNotProcessCommand extends \RuntimeException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}

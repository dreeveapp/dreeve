<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;

trait ProvideNotesFromPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    private static function parseNotes(array $payload): ?string
    {
        $notes = $payload['notes'] ?? null;
        if (null === $notes) {
            return null;
        }
        if (!is_string($notes)) {
            throw CouldNotDeserializeCommand::invalidPayload('"notes" must be a string.');
        }

        return '' === trim($notes) ? null : trim($notes);
    }
}

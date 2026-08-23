<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;

trait ProvideCaloriesFromPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    private static function parseCalories(array $payload): ?int
    {
        $calories = isset($payload['calories']) ? trim((string) $payload['calories']) : '';
        if ('' === $calories) {
            return null;
        }

        if (!is_numeric($calories) || (float) $calories < 0) {
            throw CouldNotDeserializeCommand::invalidPayload('The "calories" must be a positive number.');
        }

        return (int) round((float) $calories);
    }
}

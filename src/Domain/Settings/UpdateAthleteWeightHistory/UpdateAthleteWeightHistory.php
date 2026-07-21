<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteWeightHistory;

use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;

/**
 * Replaces the athlete's full weight history.
 *
 * Weight feeds derived metrics (w/kg, stress level), hence #[RequiresRebuild].
 */
#[RequiresRebuild]
final readonly class UpdateAthleteWeightHistory extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param list<array{on: string, weight: float}> $entries
     */
    private function __construct(
        private array $entries,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $entries = $payload['entries'] ?? null;
        if (!is_array($entries)) {
            throw CouldNotDeserializeCommand::invalidPayload('"entries" must be an array.');
        }

        $normalized = [];
        foreach (array_values($entries) as $index => $entry) {
            if (!is_array($entry)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Entry #%d must be an object.', $index));
            }

            $on = $entry['on'] ?? null;
            if (!is_string($on) || '' === trim($on)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Entry #%d is missing a valid "on" date.', $index));
            }

            $weight = $entry['weight'] ?? null;
            if (!is_numeric($weight)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Entry #%d is missing a valid numeric "weight".', $index));
            }

            $normalized[] = [
                'on' => $on,
                'weight' => (float) $weight,
            ];
        }

        try {
            // GeneralSettings::fromArray() stores weightHistory verbatim without
            // validating it, so an invalid entry would only blow up later at read
            // time. Construct the history here to fail fast instead.
            //
            // The unit system only decides how the stored number is interpreted,
            // never whether it is valid, so either one works for validation.
            AthleteWeightHistory::fromArray($normalized, UnitSystem::METRIC);
        } catch (\InvalidArgumentException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        return new self(
            entries: $normalized
        );
    }

    /**
     * @return list<array{on: string, weight: float}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

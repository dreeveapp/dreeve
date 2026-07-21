<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteFtpHistory;

use App\Domain\Ftp\FtpHistory;
use App\Domain\Ftp\FtpSport;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

/**
 * Replaces the athlete's FTP history for a single sport.
 *
 * Scoped per sport so that updating cycling cannot wipe running, and vice versa.
 * FTP feeds derived metrics (w/kg, stress level), hence #[RequiresRebuild].
 */
#[RequiresRebuild]
final readonly class UpdateAthleteFtpHistory extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param list<array{on: string, ftp: int}> $entries
     */
    private function __construct(
        private FtpSport $sport,
        private array $entries,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $sport = is_string($payload['sport'] ?? null) ? FtpSport::tryFrom($payload['sport']) : null;
        if (null === $sport) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('A valid "sport" is required, one of: %s.', implode(', ', array_column(FtpSport::cases(), 'value'))));
        }

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

            $ftp = $entry['ftp'] ?? null;
            // FtpHistory casts with (int), which would silently turn "abc" into 0.
            // Reject non-numeric input outright so the client gets a useful error.
            if (!is_numeric($ftp)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Entry #%d is missing a valid numeric "ftp".', $index));
            }

            $normalized[] = [
                'on' => $on,
                'ftp' => (int) $ftp,
            ];
        }

        try {
            // Always pass an explicitly keyed array. FtpHistory::fromArray() treats
            // an array with neither sport key as a legacy cycling-only history, and
            // we do not want to trip that BC shim by accident.
            FtpHistory::fromArray([$sport->value => $normalized]);
        } catch (\InvalidArgumentException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        return new self(
            sport: $sport,
            entries: $normalized
        );
    }

    public function getSport(): FtpSport
    {
        return $this->sport;
    }

    /**
     * @return list<array{on: string, ftp: int}>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

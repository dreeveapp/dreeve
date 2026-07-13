<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\AthleteSettingsPayload;
use App\Domain\Settings\GeneralSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class UpdateAthleteSettings extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param array<string, mixed> $athlete
     */
    private function __construct(
        private array $athlete,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $athlete = $payload['athlete'] ?? null;
        if (!is_array($athlete)) {
            throw CouldNotDeserializeCommand::invalidPayload('"athlete" must be an object.');
        }

        try {
            $athlete = AthleteSettingsPayload::normalize($athlete);
            GeneralSettings::fromArray(['athlete' => $athlete]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        return new self(
            athlete: $athlete
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAthlete(): array
    {
        return $this->athlete;
    }
}

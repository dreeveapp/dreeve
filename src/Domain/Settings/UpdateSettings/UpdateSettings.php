<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateSettings;

use App\Domain\Settings\AthleteSettingsPayload;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\RequiresRebuild;

#[RequiresRebuild]
final readonly class UpdateSettings extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private SettingsGroup $group,
        private array $data,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        $group = is_string($payload['group'] ?? null) ? SettingsGroup::tryFrom($payload['group']) : null;
        if (null === $group) {
            throw CouldNotDeserializeCommand::invalidPayload('A valid "group" is required.');
        }

        $data = $payload['data'] ?? [];
        if (!is_array($data)) {
            throw CouldNotDeserializeCommand::invalidPayload('"data" must be an object.');
        }

        try {
            if (SettingsGroup::GENERAL === $group && is_array($data['athlete'] ?? null)) {
                $data['athlete'] = AthleteSettingsPayload::normalize($data['athlete']);
            }
            $group->settingsFromArray($data);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            throw CouldNotDeserializeCommand::invalidPayload($e->getMessage());
        }

        return new self(
            group: $group,
            data: $data
        );
    }

    public function getGroup(): SettingsGroup
    {
        return $this->group;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}

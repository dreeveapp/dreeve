<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Settings\Api\WritableConfigResource;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Shows that a resource does not need a bespoke command: this one wraps the
 * existing UpdateSettings, which already replaces a whole settings group.
 */
final readonly class ZwiftConfigResource implements WritableConfigResource
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return 'zwift';
    }

    #[\Override]
    public function read(): array
    {
        $zwift = $this->settingsRepository->find(SettingsGroup::ZWIFT);

        return [
            'level' => $this->toOptionalInt($zwift['level'] ?? null),
            'racingScore' => $this->toOptionalInt($zwift['racingScore'] ?? null),
        ];
    }

    #[\Override]
    public function buildUpdateCommand(array $payload): Command
    {
        foreach (['level', 'racingScore'] as $field) {
            $value = $payload[$field] ?? null;
            if (null !== $value && !is_numeric($value)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('"%s" must be a number or null.', $field));
            }
        }

        return UpdateSettings::fromPayload([
            'group' => SettingsGroup::ZWIFT->value,
            'data' => [
                'level' => $payload['level'] ?? null,
                'racingScore' => $payload['racingScore'] ?? null,
            ],
        ]);
    }

    private function toOptionalInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}

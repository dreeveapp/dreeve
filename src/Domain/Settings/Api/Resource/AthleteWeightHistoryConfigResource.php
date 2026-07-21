<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Settings\Api\WritableConfigResource;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteWeightHistory\UpdateAthleteWeightHistory;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AthleteWeightHistoryConfigResource implements WritableConfigResource
{
    public function __construct(
        // Deliberately the uncached repository: command handlers write through it
        // too, so reading the state back straight after a write would otherwise
        // hit a stale CachingSettingsRepository::find() entry.
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return 'athlete/weight-history';
    }

    #[\Override]
    public function read(): array
    {
        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        $athlete = is_array($general['athlete'] ?? null) ? $general['athlete'] : [];
        $entries = is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : [];

        return [
            'unit' => $this->configuredUnit(),
            'entries' => array_values($entries),
        ];
    }

    #[\Override]
    public function buildUpdateCommand(array $payload): Command
    {
        $this->guardUnit($payload);

        return UpdateAthleteWeightHistory::fromPayload($payload);
    }

    /**
     * Stored weights are plain numbers whose meaning depends on the configured
     * unit system, so a client that assumes the wrong one would silently write
     * pounds into a kilogram history. Callers may pin the unit they are sending;
     * if it disagrees with the app, refuse rather than corrupt the history.
     *
     * @param array<string, mixed> $payload
     */
    private function guardUnit(array $payload): void
    {
        if (!array_key_exists('unit', $payload)) {
            return;
        }

        $unit = $payload['unit'];
        if (!is_string($unit) || $unit !== $this->configuredUnit()) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('Weights are stored in "%s" because the app is configured to use the %s unit system. Send matching values and either omit "unit" or set it to "%s".', $this->configuredUnit(), $this->settingsRepository->appearance()->getUnitSystem()->value, $this->configuredUnit()));
        }
    }

    private function configuredUnit(): string
    {
        return UnitSystem::IMPERIAL === $this->settingsRepository->appearance()->getUnitSystem() ? 'lb' : 'kg';
    }
}

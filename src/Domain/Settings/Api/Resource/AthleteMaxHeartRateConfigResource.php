<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Settings\Api\WritableConfigResource;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteMaxHeartRate\UpdateAthleteMaxHeartRate;
use App\Infrastructure\CQRS\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AthleteMaxHeartRateConfigResource implements WritableConfigResource
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return 'athlete/max-heart-rate';
    }

    #[\Override]
    public function read(): array
    {
        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        $athlete = is_array($general['athlete'] ?? null) ? $general['athlete'] : [];
        $formula = $athlete['maxHeartRateFormula'] ?? null;

        if (is_array($formula)) {
            return [
                'type' => UpdateAthleteMaxHeartRate::TYPE_MEASURED,
                'entries' => HeartRateRanges::toEntries($formula),
            ];
        }

        return [
            'type' => UpdateAthleteMaxHeartRate::TYPE_FORMULA,
            'formula' => is_string($formula) ? $formula : null,
        ];
    }

    #[\Override]
    public function buildUpdateCommand(array $payload): Command
    {
        return UpdateAthleteMaxHeartRate::fromPayload($payload);
    }
}

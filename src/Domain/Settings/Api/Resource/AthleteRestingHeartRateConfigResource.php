<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Settings\Api\WritableConfigResource;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteRestingHeartRate\UpdateAthleteRestingHeartRate;
use App\Infrastructure\CQRS\Command\Command;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AthleteRestingHeartRateConfigResource implements WritableConfigResource
{
    private const string DEFAULT_FORMULA = 'heuristicAgeBased';

    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[\Override]
    public function getName(): string
    {
        return 'athlete/resting-heart-rate';
    }

    #[\Override]
    public function read(): array
    {
        $general = $this->settingsRepository->find(SettingsGroup::GENERAL);
        $athlete = is_array($general['athlete'] ?? null) ? $general['athlete'] : [];
        $formula = $athlete['restingHeartRateFormula'] ?? self::DEFAULT_FORMULA;

        if (is_array($formula)) {
            return [
                'type' => UpdateAthleteRestingHeartRate::TYPE_MEASURED,
                'entries' => HeartRateRanges::toEntries($formula),
            ];
        }

        // A stored numeric, whether int or numeric string, is a fixed value.
        if (is_int($formula) || (is_string($formula) && ctype_digit($formula))) {
            return [
                'type' => UpdateAthleteRestingHeartRate::TYPE_FIXED,
                'bpm' => (int) $formula,
            ];
        }

        return [
            'type' => UpdateAthleteRestingHeartRate::TYPE_FORMULA,
            'formula' => is_string($formula) ? $formula : self::DEFAULT_FORMULA,
        ];
    }

    #[\Override]
    public function buildUpdateCommand(array $payload): Command
    {
        return UpdateAthleteRestingHeartRate::fromPayload($payload);
    }
}

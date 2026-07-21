<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteMaxHeartRate;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteMaxHeartRateCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteMaxHeartRate);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = is_array($data['athlete'] ?? null) ? $data['athlete'] : [];

        $athlete['maxHeartRateFormula'] = $command->getFormula();
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            data: $data,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\UpdateAthleteWeight;

use App\Domain\Athlete\Weight\AthleteWeightHistoryPayload;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteWeightCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteWeight);

        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            name: SettingsName::WEIGHT_HISTORY,
            value: AthleteWeightHistoryPayload::fromStoredValue($this->settingsRepository->find(SettingsGroup::GENERAL, SettingsName::WEIGHT_HISTORY))
                ->with($command->getOn(), $command->getWeight())
                ->toArray(),
        );
    }
}

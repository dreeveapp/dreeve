<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateSettings;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateSettingsCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateSettings);

        $data = $command->getData();
        if (SettingsGroup::ATHLETE_WEIGHT === $command->getGroup()) {
            // The manual editor must neither erase nor replace connector-owned history.
            unset($data[SettingsName::GARMIN_WEIGHT_HISTORY->value]);
            $garminHistory = $this->settingsRepository->find(SettingsName::GARMIN_WEIGHT_HISTORY);
            if (null !== $garminHistory) {
                $data[SettingsName::GARMIN_WEIGHT_HISTORY->value] = $garminHistory;
            }
        }

        $this->settingsRepository->saveGroup(
            group: $command->getGroup(),
            data: $data,
        );
    }
}

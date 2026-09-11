<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteSettingsCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteSettings);

        $data = [
            ...$this->settingsRepository->findGroup(SettingsGroup::GENERAL),
            ...$command->getAthlete(),
        ];

        $this->settingsRepository->saveGroup(
            group: SettingsGroup::GENERAL,
            data: $data,
        );
    }
}

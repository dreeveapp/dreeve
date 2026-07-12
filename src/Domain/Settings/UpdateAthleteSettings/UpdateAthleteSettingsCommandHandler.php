<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteSettingsCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteSettings);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $data['athlete'] ?? [];
        $data['athlete'] = [...$athlete, ...$command->getAthlete()];

        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            data: $data,
        );
    }
}

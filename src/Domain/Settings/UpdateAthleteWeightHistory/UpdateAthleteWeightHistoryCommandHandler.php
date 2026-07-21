<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateAthleteWeightHistory;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteWeightHistoryCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteWeightHistory);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = is_array($data['athlete'] ?? null) ? $data['athlete'] : [];

        // Only touch weightHistory, so an API client updating weight cannot
        // clobber unrelated athlete settings.
        $athlete['weightHistory'] = $command->getEntries();
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(
            group: SettingsGroup::GENERAL,
            data: $data,
        );
    }
}

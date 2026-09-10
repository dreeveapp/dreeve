<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\UpdateAthleteWeight;

use App\Domain\Athlete\Weight\AthleteWeightHistoryPayload;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateAthleteWeightCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAthleteWeight);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $data['athlete'] ?? [];

        $athlete['weightHistory'] = AthleteWeightHistoryPayload::fromStoredValue($athlete['weightHistory'] ?? null)
            ->with($command->getOn(), $command->getWeight())
            ->toArray();
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(SettingsGroup::GENERAL, $data);
    }
}

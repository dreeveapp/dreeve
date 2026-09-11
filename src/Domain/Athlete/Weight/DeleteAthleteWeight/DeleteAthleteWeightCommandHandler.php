<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\DeleteAthleteWeight;

use App\Domain\Athlete\Weight\AthleteWeightHistoryPayload;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DeleteAthleteWeightCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DeleteAthleteWeight);

        $this->settingsRepository->save(
            name: SettingsName::WEIGHT_HISTORY,
            value: AthleteWeightHistoryPayload::fromStoredValue($this->settingsRepository->find(SettingsName::WEIGHT_HISTORY))
                ->without($command->getOn())
                ->toArray(),
        );
    }
}

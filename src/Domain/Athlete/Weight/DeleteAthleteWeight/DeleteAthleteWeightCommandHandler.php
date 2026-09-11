<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\DeleteAthleteWeight;

use App\Domain\Athlete\Weight\AthleteWeightHistoryPayload;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
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

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $data['athlete'] ?? [];

        $athlete['weightHistory'] = AthleteWeightHistoryPayload::fromStoredValue($athlete['weightHistory'] ?? null)
            ->without($command->getOn())
            ->toArray();
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(SettingsGroup::GENERAL, $data);
    }
}

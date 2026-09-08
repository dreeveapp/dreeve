<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\UpsertAthleteWeight;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpsertAthleteWeightCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpsertAthleteWeight);

        $data = $this->settingsRepository->find(SettingsGroup::GENERAL);
        /** @var array<string, mixed> $athlete */
        $athlete = $data['athlete'] ?? [];
        /** @var list<mixed> $weightHistory */
        $weightHistory = is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : [];
        $on = $command->getOn()->format('Y-m-d');

        $weightHistory = array_values(array_filter(
            $weightHistory,
            static fn (mixed $entry): bool => !is_array($entry) || $on !== ($entry['on'] ?? null),
        ));
        $weightHistory[] = ['on' => $on, 'weight' => $command->getWeight()];
        $athlete['weightHistory'] = $weightHistory;
        $data['athlete'] = $athlete;

        $this->settingsRepository->save(SettingsGroup::GENERAL, $data);
    }
}

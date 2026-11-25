<?php

declare(strict_types=1);

namespace App\Domain\Athlete\Weight\ImportGarminWeights;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ImportGarminWeightsCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportGarminWeights);
        if (!$this->settingsRepository->athleteWeight()->isGarminWeightSyncEnabled()) {
            throw new \InvalidArgumentException('Garmin weight sync is disabled.');
        }
        $stored = $this->settingsRepository->find(SettingsName::GARMIN_WEIGHT_HISTORY);
        $history = [];
        foreach (is_array($stored) ? $stored : [] as $entry) {
            if (is_array($entry) && is_string($entry['on'] ?? null)) {
                $history[$entry['on']] = $entry;
            }
        }
        foreach ($command->getWeights() as $weight) {
            $history[$weight['on']] = $weight;
        }
        krsort($history);
        $updated = array_values($history);
        if ($updated !== ($stored ?? [])) {
            $this->settingsRepository->save(SettingsName::GARMIN_WEIGHT_HISTORY, $updated);
        }
    }
}

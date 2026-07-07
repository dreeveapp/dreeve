<?php

declare(strict_types=1);

namespace App\Domain\Settings\UpdateSettings;

use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UpdateSettingsCommandHandler implements CommandHandler
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateSettings);

        $this->settingsRepository->save(
            group: $command->getGroup(),
            data: $command->getData(),
        );
    }
}

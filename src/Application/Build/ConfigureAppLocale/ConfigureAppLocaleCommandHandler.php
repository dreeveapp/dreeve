<?php

declare(strict_types=1);

namespace App\Application\Build\ConfigureAppLocale;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Carbon\Carbon;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class ConfigureAppLocaleCommandHandler implements CommandHandler
{
    public function __construct(
        private LocaleSwitcher $localeSwitcher,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ConfigureAppLocale);

        $locale = $this->settingsRepository->appearance()->getLocale();
        $this->localeSwitcher->setLocale($locale->value);
        Carbon::setLocale($locale->value);
    }
}

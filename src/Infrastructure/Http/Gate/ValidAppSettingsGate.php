<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 80)]
final class ValidAppSettingsGate extends ConditionalRedirectGate
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    protected function shouldGuard(): bool
    {
        try {
            $this->settingsRepository->general();

            return false;
        } catch (AthleteHasNotBeenConfigured) {
            return true;
        }
    }

    protected function allowedPaths(): array
    {
        return [];
    }

    protected function redirectTo(): string
    {
        return '/admin/settings';
    }
}

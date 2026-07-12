<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsTaggedItem(priority: 80)]
final class ValidAppSettingsGate extends ConditionalRedirectGate
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        private readonly SettingsRepository $settingsRepository,
    ) {
        parent::__construct($urlGenerator);
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
        return ['/admin/login', '/admin/logout', '/admin/dispatchCommand'];
    }

    protected function redirectToRouteName(): string
    {
        return 'admin_settings_athlete';
    }
}

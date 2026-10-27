<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Integration\AI\AIProvider;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class IntegrationsSettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
    ) {
    }

    #[Route(path: '/admin/settings/integrations', name: 'admin_settings_integrations', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/settings/integrations.html.twig', [
            'dispatchCommand' => UpdateSettings::getCommandName(),
            'group' => SettingsGroup::INTEGRATIONS,
            'settings' => $this->settingsRepository->findGroup(SettingsGroup::INTEGRATIONS),
            'AIApiKey' => AIApiKey::fromServerVar(),
            'aiProviders' => AIProvider::cases(),
            'aiProvidersRequiringApiKey' => array_filter(AIProvider::cases(), static fn (AIProvider $provider): bool => $provider->requiresApiKey()),
        ]));
    }
}

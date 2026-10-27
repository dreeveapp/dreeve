<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\Config\AdminAllowedIpAddresses;
use App\Infrastructure\Http\ClientIpResolver;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Security\Admin\AdminUserName;
use App\Infrastructure\Security\Oidc\OidcConfig;
use App\Infrastructure\Security\Oidc\OidcProvider;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Security\Token\McpToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class SecuritySettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private AdminUserName $adminUserName,
        private AdminAllowedIpAddresses $adminAllowedIpAddresses,
        private ClientIpResolver $clientIpResolver,
        private ApiToken $apiKey,
        private McpToken $mcpKey,
        #[\SensitiveParameter]
        private OidcConfig $oidcConfig,
        private OidcProvider $oidcProvider,
    ) {
    }

    #[Route(path: '/admin/settings/security', name: 'admin_settings_security', methods: ['GET'], priority: 10)]
    public function handle(Request $request): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/settings/security.html.twig', [
            'dispatchCommand' => UpdateSettings::getCommandName(),
            'group' => SettingsGroup::SECURITY,
            'settings' => $this->settingsRepository->findGroup(SettingsGroup::SECURITY),
            'adminUsername' => $this->adminUserName,
            'adminAllowedIpAddresses' => $this->adminAllowedIpAddresses,
            'clientIpAddress' => $this->clientIpResolver->resolve($request),
            'apiKey' => $this->apiKey,
            'mcpKey' => $this->mcpKey,
            'oidcConfig' => $this->oidcConfig,
            'oidcRedirectUri' => $this->oidcProvider->getRedirectUri(),
        ]));
    }
}

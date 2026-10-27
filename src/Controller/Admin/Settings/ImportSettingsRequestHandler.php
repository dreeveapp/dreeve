<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Import\ImportMode;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Domain\Strava\StravaRefreshToken;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ImportSettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        #[\SensitiveParameter]
        private StravaClientId $stravaClientId,
        #[\SensitiveParameter]
        private StravaClientSecret $stravaClientSecret,
        #[\SensitiveParameter]
        private StravaRefreshToken $stravaRefreshToken,
        private ImportMode $importMode,
    ) {
    }

    #[Route(path: '/admin/settings/import', name: 'admin_settings_import', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        if (!$this->importMode->isStravaApi()) {
            throw new NotFoundHttpException('Page not found');
        }

        return new HtmlResponse($this->twig->render('html/admin/page/settings/import.html.twig', [
            'dispatchCommand' => UpdateSettings::getCommandName(),
            'group' => SettingsGroup::IMPORT,
            'settings' => $this->settingsRepository->findGroup(SettingsGroup::IMPORT),
            'stravaClientId' => $this->stravaClientId,
            'stravaClientSecret' => $this->stravaClientSecret,
            'stravaClientRefreshToken' => $this->stravaRefreshToken,
        ]));
    }
}

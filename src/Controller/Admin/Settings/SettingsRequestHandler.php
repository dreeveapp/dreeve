<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Application\AppUrl;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneConfiguration;
use App\Domain\Import\ImportMode;
use App\Domain\Integration\AI\AIApiKey;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Domain\Strava\StravaRefreshToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsController]
final readonly class SettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private AppUrl $appUrl,
        #[\SensitiveParameter]
        private StravaClientId $stravaClientId,
        #[\SensitiveParameter]
        private StravaClientSecret $stravaClientSecret,
        #[\SensitiveParameter]
        private StravaRefreshToken $stravaRefreshToken,
        private ImportMode $importMode,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/admin/settings', name: 'admin_settings_index', methods: ['GET'], priority: 10)]
    public function index(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('admin_settings', [
            'group' => SettingsGroup::GENERAL->value,
        ]));
    }

    #[Route(path: '/admin/settings/athlete', name: 'admin_settings_athlete', methods: ['GET'], priority: 10)]
    public function athlete(): Response
    {
        return new Response($this->twig->render(
            'html/admin/page/settings/athlete.html.twig',
            [
                'dispatchCommand' => UpdateAthleteSettings::getCommandName(),
                'settings' => $this->settingsRepository->find(SettingsGroup::GENERAL),
            ],
        ));
    }

    #[Route(path: '/admin/settings/{group}', name: 'admin_settings', methods: ['GET'], priority: 5)]
    public function handle(string $group): Response
    {
        $settingsGroup = SettingsGroup::tryFrom($group)
            ?? throw new NotFoundHttpException(sprintf('Unknown settings group "%s"', $group));

        if (SettingsGroup::IMPORT === $settingsGroup && !$this->importMode->isStravaApi()) {
            throw new NotFoundHttpException('Page not found');
        }

        return new Response($this->twig->render(
            sprintf('html/admin/page/settings/%s.html.twig', $settingsGroup->value),
            [
                'dispatchCommand' => UpdateSettings::getCommandName(),
                'group' => $settingsGroup,
                'settings' => $this->settingsRepository->find($settingsGroup),
                'appUrl' => $this->appUrl,
                'stravaClientId' => $this->stravaClientId,
                'stravaClientSecret' => $this->stravaClientSecret,
                'stravaClientRefreshToken' => $this->stravaRefreshToken,
                'AIApiKey' => AIApiKey::fromServerVar(),
                'sportTypes' => $this->settingsRepository->appearance()->getSportTypesSortingOrder(),
                'defaultHeartRateZones' => HeartRateZoneConfiguration::getDefaultZones(),
            ],
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Navigation\NavigationSection;
use App\Application\Navigation\SideBar;
use App\Application\PageMetadata\PageMetadata;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

final readonly class AppShell
{
    public function __construct(
        private SideBar $sideBar,
        private AppUrl $appUrl,
        private LocaleSwitcher $localeSwitcher,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function render(
        string $content,
        ?NavigationSection $navigationSection,
        PageMetadata $metadata,
    ): string {
        $general = $this->settingsRepository->general();

        return $this->twig->load('html/app-shell.html.twig')->render([
            'content' => $content,
            'metadata' => $metadata,
            'sidebar' => $this->sideBar->render($navigationSection),
            'athlete' => $general->getAthlete(),
            'profilePictureUrl' => $general->getProfilePictureUrl(),
            'subTitle' => $general->getAppSubTitle(),
            'javascriptWindowConstants' => Json::encode([
                'countries' => Countries::getNames($this->localeSwitcher->getLocale()),
                'appVersion' => AppVersion::getSemanticVersion(),
                'appUrl' => [
                    'basePath' => $this->appUrl->getBasePath() ?? '',
                ],
                'leafletConfig' => $this->settingsRepository->maps()->getLeafletConfig(),
            ]),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Navigation\NavigationSection;
use App\Application\Navigation\SideBar;
use App\Application\OpenGraph\OpenGraph;
use App\Controller\Api\Internal\ApiFragmentRequestHandler;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\RelativeUrl;
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

    public function render(string $content, ?NavigationSection $navigationSection, ?OpenGraph $openGraph): string
    {
        $appearance = $this->settingsRepository->appearance();
        $unitSystem = $appearance->getUnitSystem();

        $general = $this->settingsRepository->general();

        return $this->twig->load('html/app-shell.html.twig')->render([
            'content' => $content,
            'openGraph' => $openGraph,
            'sidebar' => $this->sideBar->render($navigationSection),
            'athlete' => $general->getAthlete(),
            'profilePictureUrl' => $general->getProfilePictureUrl(),
            'subTitle' => $general->getAppSubTitle(),
            'javascriptWindowConstants' => Json::encode([
                'countries' => Countries::getNames($this->localeSwitcher->getLocale()),
                'appUrl' => [
                    'basePath' => $this->appUrl->getBasePath() ?? '',
                ],
                'unitSystem' => [
                    'name' => $unitSystem->value,
                    'paceSymbol' => $unitSystem->paceSymbol(),
                    'distanceSymbol' => $unitSystem->distanceSymbol(),
                    'elevationSymbol' => $unitSystem->elevationSymbol(),
                ],
                'leafletConfig' => $this->settingsRepository->maps()->getLeafletConfig(),
                'pageFragment' => [
                    'baseUrl' => RelativeUrl::from(ApiFragmentRequestHandler::PATH_PREFIX.'/'.FragmentType::PAGE->value, $this->appUrl)->toRelativeUrl(),
                    'pathPattern' => ApiFragmentRequestHandler::PATH_REQUIREMENT,
                ],
            ]),
        ]);
    }
}

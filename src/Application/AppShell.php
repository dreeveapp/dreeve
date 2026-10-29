<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Navigation\NavigationSection;
use App\Application\Navigation\SideBar;
use App\Application\PageMetadata\PageMetadata;
use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class AppShell
{
    use ProvideMeasurementFormats;

    public function __construct(
        private SideBar $sideBar,
        private AppUrl $appUrl,
        private LocaleSwitcher $localeSwitcher,
        private SettingsRepository $settingsRepository,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private CacheableRenderer $cacheableRenderer,
        private QueryBus $queryBus,
        private Environment $twig,
    ) {
    }

    public function render(
        string $content,
        ?NavigationSection $navigationSection,
        PageMetadata $metadata,
    ): string {
        if (!($request = $this->requestStack->getCurrentRequest()) instanceof Request) {
            throw new \LogicException('The app shell can only be rendered for a request');
        }

        $general = $this->settingsRepository->general();

        return $this->twig->load('html/app-shell.html.twig')->render([
            'content' => $content,
            'metadata' => [
                'title' => $this->translate($metadata->getTitle()),
                'description' => null !== $metadata->getDescription()
                    ? $this->translate($metadata->getDescription())
                    : $this->cacheableRenderer->render(
                        cacheability: Cacheability::for(
                            cacheKey: 'app-shell.metatag.description',
                            cacheTags: CacheTags::of(
                                RootCacheTag::ACTIVITIES,
                                RootCacheTag::SETTINGS_GENERAL,
                                RootCacheTag::SETTINGS_APPEARANCE,
                            ),
                        ),
                        render: function () use ($general): string {
                            $activityTotals = $this->queryBus->ask(new FindActivityTotals());
                            $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

                            return implode(' · ', [
                                (string) $general->getAthlete()->getName(),
                                $this->translator->trans('{numberOfActivities} activities', ['{numberOfActivities}' => $this->formatNumber($activityTotals->getTotalActivities(), 0)]),
                                $this->formatUnitWithSymbol($activityTotals->getTotalDistance()->toUnitSystem($unitSystem), 0),
                                $this->formatUnitWithSymbol(Hour::from($activityTotals->getMovingTimeInHours()), 0),
                            ]);
                        },
                    )->getContent(),
                'path' => $this->urlGenerator->generate($request->attributes->getString('_route'), $request->attributes->all('_route_params')),
                'imagePath' => $metadata->getImagePath(),
            ],
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

    private function translate(string|TranslatableInterface $message): string
    {
        return $message instanceof TranslatableInterface ? $message->trans($this->translator) : $message;
    }
}

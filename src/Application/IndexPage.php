<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Cacheable;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

final readonly class IndexPage implements Cacheable
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private GearRepository $gearRepository,
        private ChallengeRepository $challengeRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ImageRepository $imageRepository,
        private AppUrl $appUrl,
        private LocaleSwitcher $localeSwitcher,
        private SettingsRepository $settingsRepository,
        private KeyValueStore $keyValueStore,
        private Environment $twig,
    ) {
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'index',
            cacheTags: CacheTags::of(
                // The "updated on" stamp in the sidebar is written when a build finishes.
                RootCacheTag::APP_BUILD,
                RootCacheTag::ACTIVITIES,
                RootCacheTag::ACTIVITY_IMAGES,
                RootCacheTag::CHALLENGES,
                // The top nav bar renders the workout assistant when the AI UI is enabled.
                RootCacheTag::SETTINGS_INTEGRATIONS,
                // The leaflet config every map on the page reads is embedded in window.dreeve.
                RootCacheTag::SETTINGS_MAPS,
            ),
        );
    }

    public function render(): string
    {
        $appearance = $this->settingsRepository->appearance();
        $unitSystem = $appearance->getUnitSystem();

        $general = $this->settingsRepository->general();

        $lastUpdate = null;
        try {
            $lastUpdate = SerializableDateTime::fromString((string) $this->keyValueStore->find(Key::APP_LAST_BUILD_DATE_TIME));
        } catch (EntityNotFound) {
            // The app has not been built since this key was introduced.
        }

        return $this->twig->load('html/index.html.twig')->render([
            'router' => Router::SINGLE_PAGE,
            'totalActivityCount' => $this->activityIdRepository->count(),
            'completedChallenges' => $this->challengeRepository->count(),
            'totalPhotoCount' => $this->imageRepository->count(),
            'hasGear' => $this->gearRepository->hasGear(),
            'lastUpdate' => $lastUpdate,
            'athlete' => $general->getAthlete(),
            'profilePictureUrl' => $general->getProfilePictureUrl(),
            'subTitle' => $general->getAppSubTitle(),
            'hasBestEfforts' => $this->activityBestEffortRepository->hasData(),
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
            ]),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Build\BuildIndexHtml;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\Image\ImageRepository;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Config\Leaflet\LeafletConfig;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class IndexHtml
{
    public function __construct(
        private ActivityIdRepository $activityIdRepository,
        private GearRepository $gearRepository,
        private ChallengeRepository $challengeRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ImageRepository $imageRepository,
        private EddingtonCalculator $eddingtonCalculator,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private LeafletConfig $leafletConfig,
        private AppUrl $appUrl,
        private UnitSystem $unitSystem,
        private LocaleSwitcher $localeSwitcher,
        private SettingsRepository $settingsRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(SerializableDateTime $now): array
    {
        $eddingtonNumbers = [];
        $eddingtons = $this->eddingtonCalculator->calculate($this->unitSystem);

        foreach ($eddingtons as $eddington) {
            if (!$eddington->getConfig()->showInNavBar()) {
                continue;
            }
            $eddingtonNumbers[] = $eddington->getNumber();
        }

        $general = $this->settingsRepository->general();

        return [
            'totalActivityCount' => $this->activityIdRepository->count(),
            'eddingtonNumbers' => $eddingtonNumbers,
            'completedChallenges' => $this->challengeRepository->count(),
            'totalPhotoCount' => $this->imageRepository->count(),
            'hasGear' => $this->gearRepository->hasGear(),
            'lastUpdate' => $now,
            'athlete' => $general->getAthlete(),
            'profilePictureUrl' => $general->getProfilePictureUrl(),
            'subTitle' => $general->getAppSubTitle(),
            'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
            'hasBestEfforts' => $this->activityBestEffortRepository->hasData(),
            'javascriptWindowConstants' => Json::encode([
                'countries' => Countries::getNames($this->localeSwitcher->getLocale()),
                'appUrl' => [
                    'basePath' => $this->appUrl->getBasePath() ?? '',
                ],
                'unitSystem' => [
                    'name' => $this->unitSystem->value,
                    'paceSymbol' => $this->unitSystem->paceSymbol(),
                    'distanceSymbol' => $this->unitSystem->distanceSymbol(),
                    'elevationSymbol' => $this->unitSystem->elevationSymbol(),
                ],
                'leafletConfig' => $this->leafletConfig,
            ]),
        ];
    }
}

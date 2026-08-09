<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Application\Countries;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RootCacheTag;
use App\Infrastructure\Http\Page\Page;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ActivitiesPage implements Page
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private SportTypeRepository $sportTypeRepository,
        private RecordingDeviceRepository $recordingDeviceRepository,
        private GearRepository $gearRepository,
        private Countries $countries,
        private Clock $clock,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'activities';
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(
                RootCacheTag::ACTIVITIES,
                RootCacheTag::GEAR,
            ),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/activity/activities.html.twig')->render([
            'sportTypes' => $this->sportTypeRepository->findAll(),
            'devices' => $this->recordingDeviceRepository->findAll(),
            'activityTotals' => ActivityTotals::create(
                activities: $this->activityRepository->findAll(),
                now: $this->clock->getCurrentDateTimeImmutable(),
                translator: $this->translator,
            ),
            'countries' => $this->countries->getUsedInActivities(),
            'gears' => $this->gearRepository->findAllUsed(),
        ]);
    }
}

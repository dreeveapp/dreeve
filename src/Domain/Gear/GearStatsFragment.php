<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\Activities;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Calendar\Months;
use App\Domain\Gear\FindGearStatsPerDay\FindGearStatsPerDay;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Theme\Theme;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class GearStatsFragment implements Fragment
{
    public function __construct(
        private GearRepository $gearRepository,
        private ActivityRepository $activityRepository,
        private SettingsRepository $settingsRepository,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Theme $theme,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'gear';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(
                RootCacheTag::GEAR,
                RootCacheTag::ACTIVITIES,
            ),
            ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
        );
    }

    public function render(): string
    {
        $now = $this->clock->getCurrentDateTimeImmutable();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $activities = $this->activityRepository->findAll();
        $allUsedGear = $this->gearRepository->findAllUsed();
        $gearStats = $this->queryBus->ask(new FindGearStatsPerDay());
        $allMonths = Months::create(
            startDate: $activities->getFirstActivityStartDate(),
            endDate: $now
        );

        $activeGear = $allUsedGear->filter(fn (Gear $gear): bool => !$gear->isRetired());
        $unspecifiedGear = $this->buildUnspecifiedGear($activities);
        if ($unspecifiedGear instanceof Gear) {
            $activeGear->add($unspecifiedGear);
        }

        return $this->twig->load('html/gear/gear.html.twig')->render([
            'activeGear' => $activeGear,
            'retiredGear' => $allUsedGear->filter(fn (Gear $gear): bool => $gear->isRetired()),
            'unitSystem' => $unitSystem,
            'distancePerMonthPerGearChart' => Json::encode(
                DistancePerMonthPerGearChart::create(
                    gearCollection: $allUsedGear,
                    activityCollection: $activities,
                    unitSystem: $unitSystem,
                    months: $allMonths,
                    theme: $this->theme,
                )->build()
            ),
            'distanceOverTimePerGear' => Json::encode(
                DistanceOverTimePerGearChart::create(
                    gears: $allUsedGear,
                    gearStats: $gearStats,
                    startDate: $activities->getFirstActivityStartDate(),
                    unitSystem: $unitSystem,
                    translator: $this->translator,
                    now: $now,
                    theme: $this->theme,
                )->build()
            ),
        ]);
    }

    private function buildUnspecifiedGear(Activities $activities): ?Gear
    {
        $activitiesWithoutGear = $activities->filter(fn (Activity $activity): bool => !$activity->getGearId() instanceof GearId);
        $count = count($activitiesWithoutGear);

        if (0 === $count) {
            return null;
        }

        $distanceInMeter = Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getDistance()->toMeter()->toFloat()));
        $movingTimeInSeconds = (int) $activitiesWithoutGear->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds());
        $elevation = Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getElevation()->toFloat()));
        $totalCalories = (int) $activitiesWithoutGear->sum(fn (Activity $activity): ?int => $activity->getCalories());

        return Gear::fromState(
            gearId: GearId::none(),
            distanceInMeter: $distanceInMeter,
            createdOn: SerializableDateTime::fromString('1970-01-01'),
            name: 'Unspecified',
            isRetired: false,
            type: GearType::IMPORTED,
            localImagePath: null,
            movingTime: Seconds::from($movingTimeInSeconds),
            elevation: $elevation,
            numberOfActivities: $count,
            totalCalories: $totalCalories,
            purchasePrice: null,
        );
    }
}

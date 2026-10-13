<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Calendar\Months;
use App\Domain\Gear\DistanceOverTimePerGearChart;
use App\Domain\Gear\DistancePerMonthPerGearChart;
use App\Domain\Gear\FindGearStatsPerDay\FindGearStatsPerDay;
use App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonth;
use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Gears;
use App\Domain\Gear\GearType;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Theme\Theme;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class GearStatsRequestHandler
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
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/gear', name: 'gear', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'gear',
                cacheTags: CacheTags::of(
                    RootCacheTag::GEAR,
                    RootCacheTag::ACTIVITIES,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::GEAR,
        );
    }

    private function renderFor(): string
    {
        $now = $this->clock->getCurrentDateTimeImmutable();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $activities = $this->activityRepository->findAll();
        $allUsedGear = $this->gearRepository->findAllUsed();
        $gearStats = $this->queryBus->ask(new FindGearStatsPerDay());
        $gearUsage = $this->queryBus->ask(new FindGearUsagePerMonth());
        $usedSince = SerializableDateTime::fromDateTimeImmutable($now->modify('-3 months'));
        $allMonths = Months::create(
            startDate: $activities->getFirstActivityStartDate(),
            endDate: $now
        );

        $activeGear = $allUsedGear->filter(fn (Gear $gear): bool => !$gear->isRetired());
        $activitiesWithoutGear = $activities->filter(fn (Activity $activity): bool => !$activity->getGearId() instanceof GearId);
        if (!$activitiesWithoutGear->isEmpty()) {
            $activeGear->add(Gear::fromState(
                gearId: GearId::none(),
                distanceInMeter: Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getDistance()->toMeter()->toFloat())),
                createdOn: SerializableDateTime::fromString('1970-01-01'),
                name: 'Unspecified',
                isRetired: false,
                type: GearType::IMPORTED,
                localImagePath: null,
                movingTime: Seconds::from((int) $activitiesWithoutGear->sum(fn (Activity $activity): int => $activity->getMovingTimeInSeconds())),
                elevation: Meter::from($activitiesWithoutGear->sum(fn (Activity $activity): float => $activity->getElevation()->toFloat())),
                numberOfActivities: count($activitiesWithoutGear),
                totalCalories: (int) $activitiesWithoutGear->sum(fn (Activity $activity): int => $activity->getCalories() ?? 0),
                purchasePrice: null,
            ));
        }

        $retiredGear = $allUsedGear->filter(fn (Gear $gear): bool => $gear->isRetired());
        $allGear = Gears::fromArray([...$activeGear, ...$retiredGear]);
        $mostRecentlyUsedFirst = fn (Gear $a, Gear $b): int => $gearUsage->getFor($b->getId())->getLastUsedOn() <=> $gearUsage->getFor($a->getId())->getLastUsedOn();

        return $this->twig->load('html/gear/gear.html.twig')->render([
            'gearUsage' => $gearUsage,
            'retiredGear' => $retiredGear->usort($mostRecentlyUsedFirst),
            'gearInUse' => $activeGear
                ->filter(fn (Gear $gear): bool => $gearUsage->getFor($gear->getId())->wasUsedSince($usedSince))
                ->usort($mostRecentlyUsedFirst),
            'idleGear' => $activeGear
                ->filter(fn (Gear $gear): bool => !$gearUsage->getFor($gear->getId())->wasUsedSince($usedSince))
                ->usort($mostRecentlyUsedFirst),
            'totalMovingTime' => Seconds::from((int) $allGear->sum(fn (Gear $gear): int => $gear->getMovingTime()->toInt())),
            'lastTwelveMonths' => Months::lastXMonths(12, $now),
            'unitSystem' => $unitSystem,
            'distancePerMonthPerGearChart' => Json::encode(
                DistancePerMonthPerGearChart::create(
                    gearCollection: $allUsedGear,
                    gearUsage: $gearUsage,
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
}

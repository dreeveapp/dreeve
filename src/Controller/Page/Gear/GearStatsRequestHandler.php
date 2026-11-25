<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDate;
use App\Domain\Calendar\Months;
use App\Domain\Gear\DistanceOverTimePerGearChart;
use App\Domain\Gear\DistancePerMonthPerGearChart;
use App\Domain\Gear\FindGearStatsPerDay\FindGearStatsPerDay;
use App\Domain\Gear\FindGearUsagePerMonth\FindGearUsagePerMonth;
use App\Domain\Gear\FindTotalsForActivitiesWithoutGear\FindTotalsForActivitiesWithoutGear;
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
use App\Infrastructure\Http\Response\HtmlResponse;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class GearStatsRequestHandler
{
    public function __construct(
        private GearRepository $gearRepository,
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
                ttl: $this->clock->now()->getDurationUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::GEAR,
            metadata: new PageMetadata(
                title: new TranslatableMessage('Gear'),
            ),
        );
    }

    private function renderFor(): string
    {
        $now = $this->clock->now();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $firstActivityStartDate = $this->queryBus->ask(new FindFirstActivityStartDate())->getStartDate();
        $allUsedGear = $this->gearRepository->findAllUsed();
        $gearStats = $this->queryBus->ask(new FindGearStatsPerDay());
        $gearUsage = $this->queryBus->ask(new FindGearUsagePerMonth());
        $allMonths = Months::create(
            startDate: $firstActivityStartDate,
            endDate: $now
        );

        $activeGear = $allUsedGear->filter(fn (Gear $gear): bool => !$gear->isRetired());
        $totalsWithoutGear = $this->queryBus->ask(new FindTotalsForActivitiesWithoutGear());
        if ($totalsWithoutGear->getNumberOfActivities() > 0) {
            $activeGear->add(Gear::fromState(
                gearId: GearId::none(),
                distanceInMeter: $totalsWithoutGear->getDistance(),
                createdOn: SerializableDateTime::fromString('1970-01-01'),
                name: 'Unspecified',
                isRetired: false,
                type: GearType::IMPORTED,
                mediaItemId: null,
                movingTime: $totalsWithoutGear->getMovingTime(),
                elevation: $totalsWithoutGear->getElevation(),
                numberOfActivities: $totalsWithoutGear->getNumberOfActivities(),
                totalCalories: $totalsWithoutGear->getCalories(),
                purchasePrice: null,
                notes: null,
            ));
        }

        $retiredGear = $allUsedGear->filter(fn (Gear $gear): bool => $gear->isRetired());
        $allGear = Gears::fromArray([...$activeGear, ...$retiredGear]);
        $mostRecentlyUsedFirst = fn (Gear $a, Gear $b): int => $gearUsage->getFor($b->getId())->getLastUsedOn() <=> $gearUsage->getFor($a->getId())->getLastUsedOn();

        return $this->twig->load('html/gear/gear.html.twig')->render([
            'gearUsage' => $gearUsage,
            'retiredGear' => $retiredGear->usort($mostRecentlyUsedFirst),
            'gearInUse' => $activeGear->usort($mostRecentlyUsedFirst),
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
                    startDate: $firstActivityStartDate,
                    unitSystem: $unitSystem,
                    translator: $this->translator,
                    now: $now,
                    theme: $this->theme,
                )->build()
            ),
        ]);
    }
}

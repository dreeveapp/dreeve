<?php

declare(strict_types=1);

namespace App\Controller\Page\Dashboard;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDate;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\BestPowerOutputs;
use App\Domain\Activity\Stream\PowerOutputChart;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class PowerOutputRequestHandler
{
    public function __construct(
        private ActivityPowerRepository $activityPowerRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private QueryBus $queryBus,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/dashboard/power-output', name: 'dashboard_power_output', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'dashboard.power-output',
                cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_METRICS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): ?string => $this->renderFor(),
            navigationSection: NavigationSection::DASHBOARD,
        );
    }

    private function renderFor(): ?string
    {
        $now = $this->clock->getCurrentDateTimeImmutable();

        $allYears = null;
        /** @var array<string, BestPowerOutputs> $bestPowerOutputsPerActivityType */
        $bestPowerOutputsPerActivityType = [];
        /** @var array<string, string> $powerOutputChartsPerActivityType */
        $powerOutputChartsPerActivityType = [];

        /** @var ActivityType $activityType */
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            if (!$activityType->supportsPowerData()) {
                continue; // @codeCoverageIgnore
            }
            $sportTypes = SportTypes::thatSupportPeakPowerOutputs($activityType);
            $bestAllTimePowerOutputs = $this->activityPowerRepository->findBestForSportTypes($sportTypes);

            if ($bestAllTimePowerOutputs->isEmpty()) {
                continue;
            }

            $allYears ??= Years::create(
                startDate: $this->queryBus->ask(new FindFirstActivityStartDate())->getStartDate(),
                endDate: $now
            );

            $bestPowerOutputs = BestPowerOutputs::empty();
            $bestPowerOutputs->add(
                description: $this->translator->trans('All time'),
                powerOutputs: $bestAllTimePowerOutputs,
            );
            foreach ([45, 90] as $numberOfDays) {
                $bestPowerOutputs->add(
                    description: $this->translator->trans('Last {numberOfDays} days', ['{numberOfDays}' => $numberOfDays]),
                    powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                        sportTypes: $sportTypes,
                        dateRange: DateRange::lastXDays($now, $numberOfDays)
                    )
                );
            }
            foreach ($allYears->reverse() as $year) {
                $bestPowerOutputs->add(
                    description: (string) $year,
                    powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                        sportTypes: $sportTypes,
                        dateRange: $year->getRange(),
                    )
                );
            }

            $bestPowerOutputsPerActivityType[$activityType->value] = $bestPowerOutputs;
            $powerOutputChartsPerActivityType[$activityType->value] = Json::encode(
                PowerOutputChart::create($bestPowerOutputs)->build()
            );
        }

        if (empty($bestPowerOutputsPerActivityType)) {
            return null;
        }

        return $this->twig->load('html/dashboard/power-output.html.twig')->render([
            'powerOutputChartsPerActivityType' => $powerOutputChartsPerActivityType,
            'bestPowerOutputsPerActivityType' => $bestPowerOutputsPerActivityType,
        ]);
    }
}

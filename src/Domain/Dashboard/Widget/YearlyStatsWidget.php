<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStats\FindYearlyStats;
use App\Domain\Dashboard\Widget\YearlyStats\FindYearlyStatsPerDay\FindYearlyStatsPerDay;
use App\Domain\Dashboard\Widget\YearlyStats\YearlyStatistics;
use App\Domain\Dashboard\Widget\YearlyStats\YearlyStatisticsChart;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class YearlyStatsWidget implements Widget
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private QueryBus $queryBus,
        private UnitSystem $unitSystem,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Yearly stats');
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('enableLastXYearsByDefault', 10)
            ->add('metricsDisplayOrder', array_map(fn (StatsContext $context) => $context->value, StatsContext::defaultSortingOrder()));
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('enableLastXYearsByDefault')) {
            throw new InvalidDashboardLayout('Configuration item "enableLastXYearsByDefault" is required for YearlyDistancesWidget.');
        }
        if (!is_int($configuration->get('enableLastXYearsByDefault'))) {
            throw new InvalidDashboardLayout('Configuration item "enableLastXYearsByDefault" must be an integer.');
        }
        if (!$configuration->exists('metricsDisplayOrder')) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" is required for YearlyDistancesWidget.');
        }
        if (!is_array($configuration->get('metricsDisplayOrder'))) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" must be an array.');
        }
        if (3 !== count($configuration->get('metricsDisplayOrder'))) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" must contain all 3 metrics.');
        }
        foreach ($configuration->get('metricsDisplayOrder') as $metricDisplayOrder) {
            if (!StatsContext::tryFrom($metricDisplayOrder)) {
                throw new InvalidDashboardLayout(sprintf('Configuration item "metricsDisplayOrder" contains invalid value "%s".', $metricDisplayOrder));
            }
        }
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $yearlyStatChartsPerContext = [];
        $yearlyStatistics = [];
        $allActivities = $this->enrichedActivities->findAll();
        $activitiesPerActivityType = $this->enrichedActivities->findGroupedByActivityType();

        $allYears = Years::create(
            startDate: $allActivities->getFirstActivityStartDate(),
            endDate: $now
        );

        $yearlyStats = $this->queryBus->ask(new FindYearlyStats());
        $yearlyStatsPerDay = $this->queryBus->ask(new FindYearlyStatsPerDay());

        $enableLastXYearsByDefault = $configuration->get('enableLastXYearsByDefault');
        assert(is_int($enableLastXYearsByDefault));

        foreach ($activitiesPerActivityType as $activityType => $activities) {
            if ($activities->isEmpty()) {
                continue;
            }

            $activityType = ActivityType::from($activityType);
            foreach (StatsContext::cases() as $yearlyStatsContext) {
                if (in_array($yearlyStatsContext, [StatsContext::DISTANCE, StatsContext::ELEVATION]) && !$activityType->supportsDistanceAndElevation()) {
                    continue;
                }

                $yearlyStatChartsPerContext[$yearlyStatsContext->value][$activityType->value] = Json::encode(
                    YearlyStatisticsChart::create(
                        yearStats: $yearlyStatsPerDay,
                        uniqueYears: $activitiesPerActivityType[$activityType->value]->getUniqueYears(),
                        activityType: $activityType,
                        context: $yearlyStatsContext,
                        unitSystem: $this->unitSystem,
                        translator: $this->translator,
                        now: $now,
                        enableLastXYearsByDefault: $enableLastXYearsByDefault
                    )->build()
                );
            }

            $yearlyStatistics[$activityType->value] = YearlyStatistics::create(
                yearlyStats: $yearlyStats,
                activityType: $activityType,
                years: $allYears
            );
        }
        /** @var string[] $metricsDisplayOrder */
        $metricsDisplayOrder = $configuration->get('metricsDisplayOrder');

        return $this->twig->load('html/dashboard/widget/widget--yearly-stats.html.twig')->render([
            'yearlyStatsChartsPerContext' => $yearlyStatChartsPerContext,
            'yearlyStatistics' => $yearlyStatistics,
            'metricsDisplayOrder' => array_map(
                StatsContext::from(...),
                $metricsDisplayOrder,
            ),
        ]);
    }
}

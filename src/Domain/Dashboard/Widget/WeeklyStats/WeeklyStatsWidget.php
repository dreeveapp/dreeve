<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeeklyStats;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Calendar\Weeks;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class WeeklyStatsWidget implements Widget
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Weekly stats');
    }

    public function getTemplateName(): string
    {
        return 'widget--weekly-stats';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('metricsDisplayOrder', array_map(fn (StatsContext $context) => $context->value, StatsContext::defaultSortingOrder()));
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('metricsDisplayOrder')) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" is required for WeeklyStatsWidget.');
        }
        if (!is_array($configuration->get('metricsDisplayOrder'))) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" must be an array.');
        }
        if (3 !== count(array_unique($configuration->get('metricsDisplayOrder')))) {
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
        $weeklyDistanceTimeCharts = $weeksPerActivityType = [];
        $activitiesPerActivityType = $this->enrichedActivities->findGroupedByActivityType();

        /** @var string[] $metricsDisplayOrder */
        $metricsDisplayOrder = $configuration->get('metricsDisplayOrder');

        foreach ($activitiesPerActivityType as $activityType => $activities) {
            if ($activities->isEmpty()) {
                continue; // @codeCoverageIgnore
            }

            $activityType = ActivityType::from($activityType);
            $weeks = Weeks::create(
                startDate: $activities->getFirstActivityStartDate(),
                now: $now
            );

            $weeksPerActivityType[$activityType->value] = Json::encode([
                'weeks' => $weeks,
                'sportTypes' => $activityType->getSportTypes(),
            ]);

            if ($activityType->supportsWeeklyStats() && $chartData = WeeklyStatsChart::create(
                activities: $activitiesPerActivityType[$activityType->value],
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                activityType: $activityType,
                metricsDisplayOrder: array_map(
                    StatsContext::from(...),
                    $metricsDisplayOrder,
                ),
                now: $now,
                translator: $this->translator,
            )->build()) {
                $weeklyDistanceTimeCharts[$activityType->value] = Json::encode($chartData);
            }
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'weeklyDistanceCharts' => $weeklyDistanceTimeCharts,
            'weeksPerActivityType' => $weeksPerActivityType,
        ]);
    }
}

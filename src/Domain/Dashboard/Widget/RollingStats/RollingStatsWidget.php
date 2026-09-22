<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\RollingStats;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\DependsOnCurrentDay;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class RollingStatsWidget implements Widget, DependsOnCurrentDay
{
    private const int MIN_ROLLING_WINDOW_IN_DAYS = 2;
    private const int MAX_ROLLING_WINDOW_IN_DAYS = 365;

    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Rolling stats');
    }

    public function getTemplateName(): string
    {
        return 'widget--rolling-stats';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('rollingWindowInDays', 7)
            ->add('metricsDisplayOrder', array_map(fn (StatsContext $context) => $context->value, StatsContext::defaultSortingOrder()));
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('rollingWindowInDays')) {
            throw new InvalidDashboardLayout('Configuration item "rollingWindowInDays" is required for RollingStatsWidget.');
        }
        if (!is_int($configuration->get('rollingWindowInDays'))) {
            throw new InvalidDashboardLayout('Configuration item "rollingWindowInDays" must be an integer.');
        }
        if ($configuration->get('rollingWindowInDays') < self::MIN_ROLLING_WINDOW_IN_DAYS) {
            throw new InvalidDashboardLayout(sprintf('Configuration item "rollingWindowInDays" must be set to a value of %d or greater.', self::MIN_ROLLING_WINDOW_IN_DAYS));
        }
        if ($configuration->get('rollingWindowInDays') > self::MAX_ROLLING_WINDOW_IN_DAYS) {
            throw new InvalidDashboardLayout(sprintf('Configuration item "rollingWindowInDays" must be set to a value of %d or lower.', self::MAX_ROLLING_WINDOW_IN_DAYS));
        }
        if (!$configuration->exists('metricsDisplayOrder')) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" is required for RollingStatsWidget.');
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

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $rollingStatsCharts = [];
        $activitiesPerActivityType = $this->activityRepository->findAll()->groupByActivityType($this->activityTypeRepository->findAll());

        /** @var string[] $metricsDisplayOrder */
        $metricsDisplayOrder = $configuration->get('metricsDisplayOrder');
        /** @var int $rollingWindowInDays */
        $rollingWindowInDays = $configuration->get('rollingWindowInDays');

        foreach ($activitiesPerActivityType as $activityType => $activities) {
            if ($activities->isEmpty()) {
                continue; // @codeCoverageIgnore
            }

            $activityType = ActivityType::from($activityType);
            if (!$activityType->supportsWeeklyStats()) {
                continue;
            }

            $rollingWindows = RollingWindows::create(
                startDate: $activities->getFirstActivityStartDate(),
                now: $now,
                windowInDays: $rollingWindowInDays,
            );

            if (!$chartData = RollingStatsChart::create(
                activities: $activities,
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                activityType: $activityType,
                metricsDisplayOrder: array_map(
                    StatsContext::from(...),
                    $metricsDisplayOrder,
                ),
                rollingWindows: $rollingWindows,
                translator: $this->translator,
            )->build()) {
                continue; // @codeCoverageIgnore
            }

            $rollingStatsCharts[$activityType->value] = Json::encode($chartData);
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'subtitle' => $this->translator->trans('{numberOfDays}-day rolling window', ['{numberOfDays}' => $rollingWindowInDays]),
            'rollingStatsCharts' => $rollingStatsCharts,
        ]);
    }
}

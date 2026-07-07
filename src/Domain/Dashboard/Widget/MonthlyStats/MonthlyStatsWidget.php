<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\MonthlyStats;

use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Calendar\FindMonthlyStats\FindMonthlyStats;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\StatsContext;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MonthlyStatsWidget implements Widget
{
    public function __construct(
        private ActivityTypeRepository $activityTypeRepository,
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Monthly stats');
    }

    public function getTemplateName(): string
    {
        return 'widget--monthly-stats';
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
            throw new InvalidDashboardLayout('Configuration item "enableLastXYearsByDefault" is required for MonthlyStatsWidget.');
        }
        if (!is_int($configuration->get('enableLastXYearsByDefault'))) {
            throw new InvalidDashboardLayout('Configuration item "enableLastXYearsByDefault" must be an integer.');
        }
        if (!$configuration->exists('metricsDisplayOrder')) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" is required for MonthlyStatsWidget.');
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
        $activityTypes = $this->activityTypeRepository->findAll();

        $monthlyStatChartsPerContext = [];
        $monthlyStats = $this->queryBus->ask(new FindMonthlyStats());

        /** @var int $enableLastXYearsByDefault */
        $enableLastXYearsByDefault = $configuration->get('enableLastXYearsByDefault');
        foreach (StatsContext::cases() as $monthlyStatsContext) {
            foreach ($activityTypes as $activityType) {
                if (in_array($monthlyStatsContext, [StatsContext::DISTANCE, StatsContext::ELEVATION]) && !$activityType->supportsDistanceAndElevation()) {
                    continue;
                }

                $monthlyStatChartsPerContext[$monthlyStatsContext->value][$activityType->value] = Json::encode(
                    MonthlyStatsChart::create(
                        activityType: $activityType,
                        monthlyStats: $monthlyStats,
                        context: $monthlyStatsContext,
                        unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                        translator: $this->translator,
                        enableLastXYearsByDefault: $enableLastXYearsByDefault
                    )->build()
                );
            }
        }

        /** @var string[] $metricsDisplayOrder */
        $metricsDisplayOrder = $configuration->get('metricsDisplayOrder');

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'monthlyStatsChartsPerContext' => $monthlyStatChartsPerContext,
            'metricsDisplayOrder' => array_map(
                StatsContext::from(...),
                $metricsDisplayOrder,
            ),
        ]);
    }
}

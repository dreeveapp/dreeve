<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\ActivityGrid;

use App\Domain\Activity\DailyTrainingLoad;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\ActivityGrid\FindCaloriesBurnedPerDay\FindCaloriesBurnedPerDay;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Rewind\FindMovingTimePerDay\FindMovingTimePerDay;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ActivityGridWidget implements Widget
{
    public function __construct(
        private DailyTrainingLoad $trainingLoad,
        private QueryBus $queryBus,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Activity heatmap');
    }

    public function getTemplateName(): string
    {
        return 'widget--activity-grid';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('metricsDisplayOrder', array_map(fn (ActivityGridType $type) => $type->value, ActivityGridType::cases()));
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('metricsDisplayOrder')) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" is required for ActivityGridWidget.');
        }
        if (!is_array($configuration->get('metricsDisplayOrder'))) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" must be an array.');
        }
        if (3 !== count(array_unique($configuration->get('metricsDisplayOrder')))) {
            throw new InvalidDashboardLayout('Configuration item "metricsDisplayOrder" must contain all 3 grid types.');
        }
        foreach ($configuration->get('metricsDisplayOrder') as $metricDisplayOrder) {
            if (!ActivityGridType::tryFrom($metricDisplayOrder)) {
                throw new InvalidDashboardLayout(sprintf('Configuration item "metricsDisplayOrder" contains invalid value "%s".', $metricDisplayOrder));
            }
        }
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $fromDate = SerializableDateTime::fromString($now->modify('-11 months')->format('Y-m-01'));
        $toDate = SerializableDateTime::fromString($now->format('Y-m-t 23:59:59'));

        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod(
            $fromDate,
            $interval,
            $toDate,
        );

        $years = Years::create(
            startDate: $fromDate,
            endDate: $toDate
        );

        $movingTimePerDay = $this->queryBus->ask(new FindMovingTimePerDay($years))->getMovingTimePerDayInMinutes();
        $caloriesBurnedPerDay = $this->queryBus->ask(new FindCaloriesBurnedPerDay($years))->getCaloriesBurnedPerDay();

        $activityGrids = [];
        foreach (ActivityGridType::cases() as $activityGridType) {
            $activityGrids[$activityGridType->value] = ActivityGrid::create(
                activityGridType: $activityGridType,
                translator: $this->translator
            );
        }

        foreach ($period as $dt) {
            $on = SerializableDateTime::fromDateTimeImmutable($dt);
            $activityGrids[ActivityGridType::LOAD->value]->add(
                on: $on,
                value: $this->trainingLoad->calculate($on)
            );
            $activityGrids[ActivityGridType::MOVING_TIME->value]->add(
                on: $on,
                value: $movingTimePerDay[$on->format('Y-m-d')] ?? 0
            );
            $activityGrids[ActivityGridType::CALORIES_BURNED->value]->add(
                on: $on,
                value: $caloriesBurnedPerDay[$on->format('Y-m-d')] ?? 0
            );
        }

        /** @var string[] $metricsDisplayOrder */
        $metricsDisplayOrder = $configuration->get('metricsDisplayOrder');

        $activityGridsCharts = [];
        foreach (ActivityGridType::cases() as $activityGridType) {
            $activityGridsCharts[$activityGridType->value] = Json::encode(ActivityGridChart::create(
                activityGrid: $activityGrids[$activityGridType->value],
                fromDate: $fromDate,
                toDate: $toDate,
                translator: $this->translator
            )->build());
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'gridCharts' => $activityGridsCharts,
            'metricsDisplayOrder' => array_map(
                ActivityGridType::from(...),
                $metricsDisplayOrder,
            ),
        ]);
    }
}

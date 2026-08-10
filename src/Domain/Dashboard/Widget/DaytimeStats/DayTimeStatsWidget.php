<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class DayTimeStatsWidget implements Widget
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Daytime stats');
    }

    public function getTemplateName(): string
    {
        return 'widget--day-time-stats';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $statsPerActivityType = [];
        $allActivities = $this->activityRepository->findAll();
        $activitiesPerActivityType = $allActivities->groupByActivityType($this->activityTypeRepository->findAll());
        if (count($activitiesPerActivityType) > 1) {
            foreach ($activitiesPerActivityType as $activityType => $activities) {
                $dayTimeStats = DaytimeStats::create($activities);
                $statsPerActivityType[$activityType] = [
                    'chart' => Json::encode(
                        DaytimeStatsChart::create(
                            daytimeStats: $dayTimeStats,
                            translator: $this->translator,
                        )->build(),
                    ),
                    'dayTimeStats' => $dayTimeStats,
                ];
            }
        }

        $allDayTimeStats = DaytimeStats::create($allActivities);

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'allActivities' => [
                'chart' => Json::encode(
                    DaytimeStatsChart::create(
                        daytimeStats: $allDayTimeStats,
                        translator: $this->translator,
                    )->build(),
                ),
                'dayTimeStats' => $allDayTimeStats,
            ],
            'statsPerActivityType' => $statsPerActivityType,
        ]);
    }
}

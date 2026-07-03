<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class WeekdayStatsWidget implements Widget
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Stats per weekday');
    }

    public function getTemplateName(): string
    {
        return 'widget--weekday-stats';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $statsPerActivityType = [];
        $activitiesPerActivityType = $this->enrichedActivities->findGroupedByActivityType();
        if (count($activitiesPerActivityType) > 1) {
            foreach ($activitiesPerActivityType as $activityType => $activities) {
                $weekdayStats = WeekdayStats::create(
                    activities: $activities,
                    translator: $this->translator,
                );
                $statsPerActivityType[$activityType] = [
                    'chart' => Json::encode(
                        WeekdayStatsChart::create($weekdayStats)->build(),
                    ),
                    'weekDayStats' => $weekdayStats,
                ];
            }
        }

        $allActivities = $this->enrichedActivities->findAll();
        $allWeekdayStats = WeekdayStats::create(
            activities: $allActivities,
            translator: $this->translator,
        );

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'allActivities' => [
                'chart' => Json::encode(
                    WeekdayStatsChart::create($allWeekdayStats)->build(),
                ),
                'weekDayStats' => $allWeekdayStats,
            ],
            'statsPerActivityType' => $statsPerActivityType,
        ]);
    }
}

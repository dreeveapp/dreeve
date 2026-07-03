<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class DayTimeStatsWidget implements Widget
{
    public function __construct(
        private EnrichedActivities $enrichedActivities,
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

        $allActivities = $this->enrichedActivities->findAll();
        $allDayTimeStats = DaytimeStats::create($allActivities);

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
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

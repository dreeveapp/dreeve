<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\WeekdayStats;

use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\WeekdayStats\FindWeekdayStats\FindWeekdayStats;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Dashboard\Widget\WidgetRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class WeekdayStatsWidget implements Widget
{
    public function __construct(
        private QueryBus $queryBus,
        private ActivityTypeRepository $activityTypeRepository,
        private WidgetRenderer $widgetRenderer,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Stats per weekday');
    }

    public function getAdminLabel(): string
    {
        return $this->getLabel();
    }

    public function getTemplateName(): string
    {
        return 'widget--weekday-stats';
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
        $weekdayStats = $this->queryBus->ask(new FindWeekdayStats());

        $statsPerActivityType = [];
        $activityTypes = $this->activityTypeRepository->findAll();
        if (count($activityTypes) > 1) {
            foreach ($activityTypes as $activityType) {
                $statsPerWeekday = $weekdayStats->getStatsPerWeekday($activityType);
                $statsPerActivityType[$activityType->value] = [
                    'chart' => Json::encode(
                        WeekdayStatsChart::create(
                            statsPerWeekday: $statsPerWeekday,
                            translator: $this->translator,
                        )->build(),
                    ),
                    'statsPerWeekday' => $statsPerWeekday,
                ];
            }
        }

        $allStatsPerWeekday = $weekdayStats->getStatsPerWeekday(null);

        return $this->widgetRenderer->render($this, $configuration, [
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'allActivities' => [
                'chart' => Json::encode(
                    WeekdayStatsChart::create(
                        statsPerWeekday: $allStatsPerWeekday,
                        translator: $this->translator,
                    )->build(),
                ),
                'statsPerWeekday' => $allStatsPerWeekday,
            ],
            'statsPerActivityType' => $statsPerActivityType,
        ]);
    }
}

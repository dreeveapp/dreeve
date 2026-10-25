<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DaytimeStats;

use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\DaytimeStats\FindDaytimeStats\FindDaytimeStats;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Dashboard\Widget\WidgetRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DayTimeStatsWidget implements Widget
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
        return $this->translator->trans('Daytime stats');
    }

    public function getAdminLabel(): string
    {
        return $this->getLabel();
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
        return WidgetConfiguration::empty()
            ->add(Daytime::MORNING->getStartHourConfigKey(), 6)
            ->add(Daytime::AFTERNOON->getStartHourConfigKey(), 12)
            ->add(Daytime::EVENING->getStartHourConfigKey(), 17)
            ->add(Daytime::NIGHT->getStartHourConfigKey(), 23);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        $startHours = [];
        foreach (Daytime::cases() as $daytime) {
            $key = $daytime->getStartHourConfigKey();
            if (!$configuration->exists($key)) {
                throw new InvalidDashboardLayout(sprintf('Configuration item "%s" is required for DayTimeStatsWidget.', $key));
            }

            $startHour = $configuration->get($key);
            if (!is_int($startHour)) {
                throw new InvalidDashboardLayout(sprintf('Configuration item "%s" must be an integer.', $key));
            }

            if ($startHour < 0 || $startHour > 23) {
                throw new InvalidDashboardLayout(sprintf('Configuration item "%s" must be between 0 and 23.', $key));
            }

            $startHours[$daytime->value] = $startHour;
        }

        $previousHoursSinceMorning = 0;
        foreach ([Daytime::AFTERNOON, Daytime::EVENING, Daytime::NIGHT] as $daytime) {
            $hoursSinceMorning = ($startHours[$daytime->value] - $startHours[Daytime::MORNING->value] + 24) % 24;
            if ($hoursSinceMorning <= $previousHoursSinceMorning) {
                throw new InvalidDashboardLayout('The daytime start hours must follow each other in the order morning, afternoon, evening, night.');
            }
            $previousHoursSinceMorning = $hoursSinceMorning;
        }
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $startHours = DaytimeStartHours::create(
            morning: (int) $configuration->get(Daytime::MORNING->getStartHourConfigKey()),
            afternoon: (int) $configuration->get(Daytime::AFTERNOON->getStartHourConfigKey()),
            evening: (int) $configuration->get(Daytime::EVENING->getStartHourConfigKey()),
            night: (int) $configuration->get(Daytime::NIGHT->getStartHourConfigKey()),
        );
        $daytimeStats = $this->queryBus->ask(new FindDaytimeStats());

        $statsPerActivityType = [];
        $activityTypes = $this->activityTypeRepository->findAll();
        if (count($activityTypes) > 1) {
            foreach ($activityTypes as $activityType) {
                $statsPerActivityType[$activityType->value] = [
                    'chart' => Json::encode(
                        DaytimeStatsChart::create(
                            numberOfActivitiesPerHour: $daytimeStats->getNumberOfActivitiesPerHour($startHours, $activityType),
                            startHours: $startHours,
                            translator: $this->translator,
                        )->build(),
                    ),
                    'statsPerDaytime' => $daytimeStats->getStatsPerDaytime($startHours, $activityType),
                ];
            }
        }

        return $this->widgetRenderer->render($this, $configuration, [
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'allActivities' => [
                'chart' => Json::encode(
                    DaytimeStatsChart::create(
                        numberOfActivitiesPerHour: $daytimeStats->getNumberOfActivitiesPerHour($startHours, null),
                        startHours: $startHours,
                        translator: $this->translator,
                    )->build(),
                ),
                'statsPerDaytime' => $daytimeStats->getStatsPerDaytime($startHours, null),
            ],
            'statsPerActivityType' => $statsPerActivityType,
        ]);
    }
}

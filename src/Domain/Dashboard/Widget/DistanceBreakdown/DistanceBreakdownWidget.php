<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DistanceBreakdown;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class DistanceBreakdownWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private ActivityRepository $activityRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Distance breakdown');
    }

    public function getTemplateName(): string
    {
        return 'widget--distance-breakdown';
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
        $activitiesPerActivityType = $this->activityRepository->findAll()->groupByActivityType($this->activityTypeRepository->findAll());

        $distanceBreakdowns = [];
        foreach ($activitiesPerActivityType as $activityType => $activities) {
            if ($activities->isEmpty()) {
                continue;
            }

            $activityType = ActivityType::from($activityType);
            if (!$activityType->supportsDistanceBreakdownStats()) {
                continue;
            }

            $distanceBreakdown = DistanceBreakdown::create(
                activities: $activitiesPerActivityType[$activityType->value],
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem()
            );

            if ($build = $distanceBreakdown->build()) {
                $distanceBreakdowns[$activityType->value] = $build;
            }
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'distanceBreakdowns' => $distanceBreakdowns,
        ]);
    }
}

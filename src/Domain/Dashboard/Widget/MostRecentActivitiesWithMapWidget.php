<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\LeafletMap;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MostRecentActivitiesWithMapWidget implements Widget
{
    private const int MAX_NUMBER_OF_ACTIVITIES_TO_DISPLAY = 3;

    public function __construct(
        private TranslatorInterface $translator,
        private ActivityRepository $activityRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Most recent activities with map');
    }

    public function getTemplateName(): string
    {
        return 'widget--most-recent-activities-with-map';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::ACTIVITY_ROUTE);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('numberOfActivitiesToDisplay', 1)
            ->add('onlyShowActivitiesWithAMap', false);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('numberOfActivitiesToDisplay')) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" is required for MostRecentActivitiesWithMapWidget.');
        }

        if (!is_int($configuration->get('numberOfActivitiesToDisplay'))) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" must be an integer.');
        }

        if ($configuration->get('numberOfActivitiesToDisplay') < 1) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.');
        }

        if ($configuration->get('numberOfActivitiesToDisplay') > self::MAX_NUMBER_OF_ACTIVITIES_TO_DISPLAY) {
            throw new InvalidDashboardLayout(sprintf('Configuration item "numberOfActivitiesToDisplay" must be set to a value of %d or lower.', self::MAX_NUMBER_OF_ACTIVITIES_TO_DISPLAY));
        }

        if (!$configuration->exists('onlyShowActivitiesWithAMap')) {
            throw new InvalidDashboardLayout('Configuration item "onlyShowActivitiesWithAMap" is required for MostRecentActivitiesWithMapWidget.');
        }

        if (!is_bool($configuration->get('onlyShowActivitiesWithAMap'))) {
            throw new InvalidDashboardLayout('Configuration item "onlyShowActivitiesWithAMap" must be a boolean.');
        }
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $activities = $this->activityRepository->findMostRecent(
            limit: (int) $configuration->get('numberOfActivitiesToDisplay'),
            onlyActivitiesWithARoute: (bool) $configuration->get('onlyShowActivitiesWithAMap'),
        );

        if ($activities->isEmpty()) {
            return null;
        }

        $items = [];
        foreach ($activities as $activity) {
            $leafletMap = $activity->getLeafletMap();
            $items[] = [
                'activity' => $activity,
                'leaflet' => $leafletMap instanceof LeafletMap ? [
                    'polylineUrl' => ActivityFragmentPath::for($activity->getId(), 'polylines'),
                    'map' => $leafletMap,
                ] : null,
            ];
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'items' => $items,
        ]);
    }
}

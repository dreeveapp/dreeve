<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Milestone\MilestoneCollector;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MostRecentMilestonesWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private MilestoneCollector $milestonesCollector,
        private ActivityRepository $activityRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Most recent milestones');
    }

    public function getTemplateName(): string
    {
        return 'widget--most-recent-milestones';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::GEAR, RootCacheTag::SETTINGS_METRICS);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('numberOfMilestonesToDisplay', 5);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('numberOfMilestonesToDisplay')) {
            throw new InvalidDashboardLayout('Configuration item "numberOfMilestonesToDisplay" is required for MostRecentMilestonesWidget.');
        }

        if (!is_int($configuration->get('numberOfMilestonesToDisplay'))) {
            throw new InvalidDashboardLayout('Configuration item "numberOfMilestonesToDisplay" must be an integer.');
        }

        if ($configuration->get('numberOfMilestonesToDisplay') < 1) {
            throw new InvalidDashboardLayout('Configuration item "numberOfMilestonesToDisplay" must be set to a value of 1 or greater.');
        }
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $milestones = $this->milestonesCollector->discoverAll();
        if ($milestones->isEmpty()) {
            return null;
        }

        $numberOfMilestonesToDisplay = (int) $configuration->get('numberOfMilestonesToDisplay');
        $milestones = $milestones->slice(0, $numberOfMilestonesToDisplay);

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'milestones' => $milestones,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($milestones->getActivityIds())->keyByActivityId(),
        ]);
    }
}

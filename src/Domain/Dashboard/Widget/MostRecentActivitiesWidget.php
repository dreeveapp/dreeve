<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MostRecentActivitiesWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private EnrichedActivities $enrichedActivities,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Most recent activities');
    }

    public function getTemplateName(): string
    {
        return 'widget--most-recent-activities';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('numberOfActivitiesToDisplay', 5);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('numberOfActivitiesToDisplay')) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" is required for MostRecentActivitiesWidget.');
        }

        if (!is_int($configuration->get('numberOfActivitiesToDisplay'))) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" must be an integer.');
        }

        if ($configuration->get('numberOfActivitiesToDisplay') < 1) {
            throw new InvalidDashboardLayout('Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.');
        }
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $allActivities = $this->enrichedActivities->findAll();

        $numberOfActivitiesToDisplay = (int) $configuration->get('numberOfActivitiesToDisplay');

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'mostRecentActivities' => $allActivities->slice(0, $numberOfActivitiesToDisplay),
        ]);
    }
}

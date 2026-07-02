<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget\DistanceBreakdown;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Dashboard\Widget\Widget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class DistanceBreakdownWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private EnrichedActivities $enrichedActivities,
        private Environment $twig,
        private UnitSystem $unitSystem,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Distance breakdown');
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
        $activitiesPerActivityType = $this->enrichedActivities->findGroupedByActivityType();

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
                unitSystem: $this->unitSystem
            );

            if ($build = $distanceBreakdown->build()) {
                $distanceBreakdowns[$activityType->value] = $build;
            }
        }

        return $this->twig->load('html/dashboard/widget/widget--distance-breakdown.html.twig')->render([
            'distanceBreakdowns' => $distanceBreakdowns,
        ]);
    }
}

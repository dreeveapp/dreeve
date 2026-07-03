<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Milestone\MilestoneCollector;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MostRecentMilestonesWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private MilestoneCollector $milestonesCollector,
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

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $milestones = $this->milestonesCollector->discoverAll();
        if ($milestones->isEmpty()) {
            return null;
        }

        $numberOfMilestonesToDisplay = (int) $configuration->get('numberOfMilestonesToDisplay');

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'milestones' => $milestones->slice(0, $numberOfMilestonesToDisplay),
        ]);
    }
}

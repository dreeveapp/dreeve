<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\UpcomingMaintenance\UpcomingMaintenanceTask;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class UpcomingMaintenanceWidget implements Widget, DependsOnCurrentDay
{
    public function __construct(
        private TranslatorInterface $translator,
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private GearIdRepository $gearIdRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Upcoming maintenance');
    }

    public function getTemplateName(): string
    {
        return 'widget--upcoming-maintenance';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::GEAR_MAINTENANCE, RootCacheTag::ACTIVITIES, RootCacheTag::GEAR);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('numberOfTasksToDisplay', 5);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('numberOfTasksToDisplay')) {
            throw new InvalidDashboardLayout('Configuration item "numberOfTasksToDisplay" is required for UpcomingMaintenanceWidget.');
        }

        if (!is_int($configuration->get('numberOfTasksToDisplay'))) {
            throw new InvalidDashboardLayout('Configuration item "numberOfTasksToDisplay" must be an integer.');
        }

        if ($configuration->get('numberOfTasksToDisplay') < 1) {
            throw new InvalidDashboardLayout('Configuration item "numberOfTasksToDisplay" must be set to a value of 1 or greater.');
        }
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $gearMaintenanceConfig = $this->gearMaintenanceRepository->find();
        if (!$gearMaintenanceConfig->isFeatureEnabled()) {
            return null;
        }

        $maintenanceTaskStatuses = $this->maintenanceTaskProgressCalculator->calculateStatuses();
        $retiredGearIds = $gearMaintenanceConfig->ignoreRetiredGear()
            ? $this->gearIdRepository->findRetired()
            : GearIds::empty();

        $upcomingMaintenanceTasks = [];
        foreach ($gearMaintenanceConfig->getGearComponents() as $gearComponent) {
            if ($this->isOnlyAttachedToRetiredGear($gearComponent, $retiredGearIds)) {
                continue;
            }

            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                // A task that was never performed has no anchor date, so there is no countdown to show.
                if (!$progress = $maintenanceTaskStatuses->getForTask($maintenanceTask->getId())?->getProgress()) {
                    continue;
                }

                $upcomingMaintenanceTasks[] = UpcomingMaintenanceTask::from(
                    componentLabel: $gearComponent->getLabel(),
                    taskLabel: $maintenanceTask->getLabel(),
                    intervalValue: $maintenanceTask->getIntervalValue(),
                    intervalUnit: $maintenanceTask->getIntervalUnit(),
                    progress: $progress,
                );
            }
        }

        if (!$upcomingMaintenanceTasks) {
            return null;
        }

        // The completion ratio is not capped at 100%, so overdue tasks still rank among themselves.
        usort(
            $upcomingMaintenanceTasks,
            static fn (UpcomingMaintenanceTask $a, UpcomingMaintenanceTask $b): int => $b->getProgress()->getCompletionRatio() <=> $a->getProgress()->getCompletionRatio(),
        );

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'upcomingMaintenanceTasks' => array_slice($upcomingMaintenanceTasks, 0, (int) $configuration->get('numberOfTasksToDisplay')),
        ]);
    }

    private function isOnlyAttachedToRetiredGear(GearComponent $gearComponent, GearIds $retiredGearIds): bool
    {
        foreach ($gearComponent->getAttachedTo() as $gearId) {
            if (!$retiredGearIds->has($gearId)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\Action\ConfiguredAction\ConfiguredAction;
use App\Domain\Automation\AutomationRule;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;

final readonly class GearUsage
{
    public function __construct(
        private GearMaintenanceRepository $gearMaintenanceRepository,
        private GearMaintenanceLogRepository $gearMaintenanceLogRepository,
        private AutomationRuleRepository $automationRuleRepository,
    ) {
    }

    public function isInUse(Gear $gear): bool
    {
        $gearId = $gear->getId();

        if ($gear->getNumberOfActivities() > 0) {
            return true;
        }

        if ($this->gearMaintenanceRepository->find()->getAllReferencedGearIds()->has($gearId)) {
            return true;
        }

        if ($this->gearMaintenanceLogRepository->existsForGear($gearId)) {
            return true;
        }

        return null !== $this->automationRuleRepository->findAll()->find(
            static fn (AutomationRule $automationRule): bool => null !== $automationRule->getActions()->find(
                static fn (ConfiguredAction $action): bool => ActionType::ASSIGN_GEAR === $action->getType()
                    && GearId::fromString($action->getConfiguration()->getString('gearId'))->matches($gearId)
            )
        );
    }
}

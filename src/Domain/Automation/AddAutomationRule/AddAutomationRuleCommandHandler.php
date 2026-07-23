<?php

declare(strict_types=1);

namespace App\Domain\Automation\AddAutomationRule;

use App\Domain\Automation\AutomationRule;
use App\Domain\Automation\AutomationRuleComponents;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\InvalidAutomationRule;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\Time\Clock\Clock;

final readonly class AddAutomationRuleCommandHandler implements CommandHandler
{
    public function __construct(
        private AutomationRuleComponents $components,
        private AutomationRuleRepository $automationRuleRepository,
        private Clock $clock,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof AddAutomationRule);

        try {
            $conditions = $this->components->buildConditions($command->getConditions());
            $actions = $this->components->buildActions($command->getActions());
        } catch (InvalidAutomationRule $e) {
            throw CouldNotProcessCommand::withReason($e->getMessage());
        }

        $this->automationRuleRepository->add(AutomationRule::create(
            automationRuleId: AutomationRuleId::random(),
            label: $command->getLabel(),
            isEnabled: $command->isEnabled(),
            stopProcessing: $command->stopProcessing(),
            sortOrder: $this->automationRuleRepository->findAll()->count(),
            conditions: $conditions,
            actions: $actions,
            createdOn: $this->clock->getCurrentDateTimeImmutable(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation\UpdateAutomationRule;

use App\Domain\Automation\AutomationRuleComponents;
use App\Domain\Automation\AutomationRuleRepository;
use App\Domain\Automation\InvalidAutomationRule;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;

final readonly class UpdateAutomationRuleCommandHandler implements CommandHandler
{
    public function __construct(
        private AutomationRuleComponents $components,
        private AutomationRuleRepository $automationRuleRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateAutomationRule);

        $automationRule = $this->automationRuleRepository->find($command->getAutomationRuleId());

        try {
            $conditions = $this->components->buildConditions($command->getConditions());
            $actions = $this->components->buildActions($command->getActions());
        } catch (InvalidAutomationRule $e) {
            throw CouldNotProcessCommand::withReason($e->getMessage());
        }

        $this->automationRuleRepository->update(
            $automationRule
                ->withLabel($command->getLabel())
                ->withIsEnabled($command->isEnabled())
                ->withStopProcessing($command->stopProcessing())
                ->withConditions($conditions)
                ->withActions($actions),
        );
    }
}

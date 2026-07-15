<?php

declare(strict_types=1);

namespace App\Domain\Automation\SaveAutomationRuleOrder;

use App\Domain\Automation\AutomationRuleRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class SaveAutomationRuleOrderCommandHandler implements CommandHandler
{
    public function __construct(
        private AutomationRuleRepository $automationRuleRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof SaveAutomationRuleOrder);

        $this->automationRuleRepository->updateOrder($command->getOrderedIds());
    }
}

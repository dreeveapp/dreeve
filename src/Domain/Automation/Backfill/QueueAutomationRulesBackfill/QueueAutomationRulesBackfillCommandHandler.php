<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill\QueueAutomationRulesBackfill;

use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\AutomationRulesBackfillRequest;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class QueueAutomationRulesBackfillCommandHandler implements CommandHandler
{
    public function __construct(
        private AutomationRulesBackfillQueue $queue,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof QueueAutomationRulesBackfill);

        $this->queue->queue(AutomationRulesBackfillRequest::fromState(
            $command->getAutomationRuleIds(),
            $command->getActivityIds(),
        ));
    }
}

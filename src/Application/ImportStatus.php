<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Import\WatchDirectory;

final readonly class ImportStatus
{
    public function __construct(
        private WatchDirectory $watchDirectory,
        private AutomationRulesBackfillQueue $automationRulesBackfillQueue,
    ) {
    }

    public function isPending(): bool
    {
        if ($this->watchDirectory->hasFilesThatCanBeProcessed()) {
            return true;
        }

        return $this->automationRulesBackfillQueue->isQueued();
    }
}

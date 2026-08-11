<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill;

final readonly class AutomationRulesBackfillPreview
{
    /**
     * @param list<MatchedActivity> $matchedActivities
     */
    public function __construct(
        private int $totalScanned,
        private array $matchedActivities,
    ) {
    }

    public function getTotalScanned(): int
    {
        return $this->totalScanned;
    }

    /**
     * @return list<MatchedActivity>
     */
    public function getMatchedActivities(): array
    {
        return $this->matchedActivities;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Activity\Activity;

final readonly class AutomationRuleApplication
{
    /**
     * @param list<AutomationRuleId> $appliedRuleIds
     */
    public function __construct(
        private Activity $activity,
        private array $appliedRuleIds,
    ) {
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    /**
     * @return list<AutomationRuleId>
     */
    public function getAppliedRuleIds(): array
    {
        return $this->appliedRuleIds;
    }
}

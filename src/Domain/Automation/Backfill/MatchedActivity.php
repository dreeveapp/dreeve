<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill;

use App\Domain\Activity\Activity;
use App\Domain\Automation\AutomationRuleIds;

final readonly class MatchedActivity
{
    public function __construct(
        private Activity $activity,
        private AutomationRuleIds $appliedAutomationRuleIds,
    ) {
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getAppliedAutomationRuleIds(): AutomationRuleIds
    {
        return $this->appliedAutomationRuleIds;
    }
}

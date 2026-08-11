<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Automation\AutomationRuleEngine;
use App\Domain\Automation\AutomationRuleIds;
use App\Domain\Automation\AutomationRules;

final readonly class AutomationRulesBackfillPreviewer
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private AutomationRuleEngine $automationRuleEngine,
    ) {
    }

    public function preview(AutomationRules $rules): AutomationRulesBackfillPreview
    {
        $totalScanned = 0;
        $matchedActivities = [];

        foreach ($this->activityRepository->findAll() as $activity) {
            ++$totalScanned;

            $appliedRuleIds = $this->automationRuleEngine->applyWithReport($activity, $rules)->getAppliedRuleIds();
            if ([] === $appliedRuleIds) {
                continue;
            }

            $matchedActivities[] = new MatchedActivity($activity, AutomationRuleIds::fromArray($appliedRuleIds));
        }

        return new AutomationRulesBackfillPreview(
            totalScanned: $totalScanned,
            matchedActivities: $matchedActivities,
        );
    }
}

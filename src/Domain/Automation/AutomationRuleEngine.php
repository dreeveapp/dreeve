<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Actions;

final readonly class AutomationRuleEngine
{
    public function __construct(
        private AutomationRuleMatcher $matcher,
        private Actions $actions,
    ) {
    }

    public function apply(Activity $activity, AutomationRules $rules): Activity
    {
        return $this->applyWithReport($activity, $rules)->getActivity();
    }

    public function applyWithReport(Activity $activity, AutomationRules $rules): AutomationRuleApplication
    {
        // Conditions are always evaluated against the activity as it entered the engine,
        // so earlier rules cannot influence which later rules match.
        $originalActivity = $activity;
        $appliedRuleIds = [];

        foreach ($rules as $rule) {
            if (!$this->matcher->matches($rule, $originalActivity)) {
                continue;
            }
            $appliedRuleIds[] = $rule->getId();
            foreach ($rule->getActions() as $configuredAction) {
                if (!$this->actions->has($configuredAction->getType())) {
                    continue;
                }
                $activity = $this->actions->get($configuredAction->getType())
                    ->applyTo($activity, $configuredAction->getConfiguration());
            }

            if ($rule->stopProcessing()) {
                break;
            }
        }

        return new AutomationRuleApplication($activity, $appliedRuleIds);
    }
}

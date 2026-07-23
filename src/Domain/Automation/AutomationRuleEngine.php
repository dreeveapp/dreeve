<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Domain\Activity\Activity;
use App\Domain\Automation\Action\Actions;

final readonly class AutomationRuleEngine
{
    public function __construct(
        private AutomationRuleRepository $automationRuleRepository,
        private AutomationRuleMatcher $matcher,
        private Actions $actions,
    ) {
    }

    public function apply(Activity $activity): Activity
    {
        // Conditions are always evaluated against the activity as it entered the engine,
        // so earlier rules cannot influence which later rules match.
        $originalActivity = $activity;

        foreach ($this->automationRuleRepository->findAll() as $rule) {
            if (!$rule->isEnabled()) {
                continue;
            }
            if (!$this->matcher->matches($rule, $originalActivity)) {
                continue;
            }
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

        return $activity;
    }
}

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
        $rule = $this->matcher->firstMatching($this->automationRuleRepository->findAll(), $activity);
        if (!$rule instanceof AutomationRule) {
            return $activity;
        }

        foreach ($rule->getActions() as $configuredAction) {
            if (!$this->actions->has($configuredAction->getType())) {
                continue;
            }
            $activity = $this->actions->get($configuredAction->getType())
                ->applyTo($activity, $configuredAction->getConfiguration());
        }

        return $activity;
    }
}

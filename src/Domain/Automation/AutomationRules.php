<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<AutomationRule>
 */
final class AutomationRules extends Collection
{
    public function getItemClassName(): string
    {
        return AutomationRule::class;
    }

    public function enabled(): self
    {
        return $this->filter(static fn (AutomationRule $automationRule): bool => $automationRule->isEnabled());
    }

    public function only(AutomationRuleIds $automationRuleIds): self
    {
        $ids = array_map(strval(...), $automationRuleIds->toArray());

        return $this->filter(static fn (AutomationRule $automationRule): bool => in_array((string) $automationRule->getId(), $ids, true));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<AutomationRuleId>
 */
final class AutomationRuleIds extends Collection
{
    public function getItemClassName(): string
    {
        return AutomationRuleId::class;
    }
}

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
}

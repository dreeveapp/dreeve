<?php

declare(strict_types=1);

namespace App\Domain\Automation;

use App\Infrastructure\ValueObject\Identifier\Identifier;

final readonly class AutomationRuleId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'automationRule-';
    }
}

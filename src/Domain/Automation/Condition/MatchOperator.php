<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

enum MatchOperator: string
{
    case IS = 'is';
    case IS_NOT = 'isNot';
    case IS_ONE_OF = 'isOneOf';
    case IS_NONE_OF = 'isNoneOf';

    public function isForSingleValue(): bool
    {
        return match ($this) {
            self::IS, self::IS_NOT => true,
            default => false,
        };
    }

    public function isForSet(): bool
    {
        return match ($this) {
            self::IS_ONE_OF, self::IS_NONE_OF => true,
            default => false,
        };
    }

    public function isSatisfiedBy(bool $matches): bool
    {
        return match ($this) {
            self::IS, self::IS_ONE_OF => $matches,
            self::IS_NOT, self::IS_NONE_OF => !$matches,
        };
    }
}

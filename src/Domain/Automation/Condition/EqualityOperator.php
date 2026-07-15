<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

enum EqualityOperator: string
{
    case IS = 'is';
    case IS_NOT = 'isNot';

    public function isSatisfiedBy(bool $isEqual): bool
    {
        return match ($this) {
            self::IS => $isEqual,
            self::IS_NOT => !$isEqual,
        };
    }
}

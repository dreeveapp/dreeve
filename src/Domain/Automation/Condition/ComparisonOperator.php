<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

enum ComparisonOperator: string
{
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case EQUAL = 'eq';

    public function isSatisfiedBy(float $actual, float $expected): bool
    {
        return match ($this) {
            self::LESS_THAN => $actual < $expected,
            self::LESS_THAN_OR_EQUAL => $actual <= $expected,
            self::GREATER_THAN => $actual > $expected,
            self::GREATER_THAN_OR_EQUAL => $actual >= $expected,
            self::EQUAL => $actual === $expected,
        };
    }
}

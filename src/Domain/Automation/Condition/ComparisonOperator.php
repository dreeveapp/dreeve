<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ComparisonOperator: string implements TranslatableInterface
{
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case EQUAL = 'eq';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::LESS_THAN => $translator->trans('less than', domain: 'admin', locale: $locale),
            self::LESS_THAN_OR_EQUAL => $translator->trans('at most', domain: 'admin', locale: $locale),
            self::GREATER_THAN => $translator->trans('greater than', domain: 'admin', locale: $locale),
            self::GREATER_THAN_OR_EQUAL => $translator->trans('at least', domain: 'admin', locale: $locale),
            self::EQUAL => $translator->trans('exactly', domain: 'admin', locale: $locale),
        };
    }

    public function transForTimeOfDay(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::LESS_THAN => $translator->trans('before', domain: 'admin', locale: $locale),
            self::LESS_THAN_OR_EQUAL => $translator->trans('at or before', domain: 'admin', locale: $locale),
            self::GREATER_THAN => $translator->trans('after', domain: 'admin', locale: $locale),
            self::GREATER_THAN_OR_EQUAL => $translator->trans('at or after', domain: 'admin', locale: $locale),
            self::EQUAL => $translator->trans('exactly at', domain: 'admin', locale: $locale),
        };
    }

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

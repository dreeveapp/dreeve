<?php

declare(strict_types=1);

namespace App\Domain\Automation\Condition;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MatchOperator: string implements TranslatableInterface
{
    case IS = 'is';
    case IS_NOT = 'isNot';
    case IS_ONE_OF = 'isOneOf';
    case IS_NONE_OF = 'isNoneOf';
    case WITHIN = 'within';
    case OUTSIDE = 'outside';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::IS => $translator->trans('is', domain: 'admin', locale: $locale),
            self::IS_NOT => $translator->trans('is not', domain: 'admin', locale: $locale),
            self::IS_ONE_OF => $translator->trans('is one of', domain: 'admin', locale: $locale),
            self::IS_NONE_OF => $translator->trans('is none of', domain: 'admin', locale: $locale),
            self::WITHIN => $translator->trans('within radius', domain: 'admin', locale: $locale),
            self::OUTSIDE => $translator->trans('outside radius', domain: 'admin', locale: $locale),
        };
    }

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

    public function isForProximity(): bool
    {
        return match ($this) {
            self::WITHIN, self::OUTSIDE => true,
            default => false,
        };
    }

    public function isSatisfiedBy(bool $matches): bool
    {
        return match ($this) {
            self::IS, self::IS_ONE_OF, self::WITHIN => $matches,
            self::IS_NOT, self::IS_NONE_OF, self::OUTSIDE => !$matches,
        };
    }
}

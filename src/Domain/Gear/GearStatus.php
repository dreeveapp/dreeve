<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum GearStatus: string implements TranslatableInterface
{
    case ACTIVE = 'active';
    case RETIRED = 'retired';

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::ACTIVE => $translator->trans('Active', domain: 'admin', locale: $locale),
            self::RETIRED => $translator->trans('Retired', domain: 'admin', locale: $locale),
        };
    }
}

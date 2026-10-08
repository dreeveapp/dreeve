<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SegmentType: string implements TranslatableInterface
{
    case IMPORTED = 'imported';
    case CUSTOM = 'custom';

    public function isImported(): bool
    {
        return self::IMPORTED === $this;
    }

    public function isCustom(): bool
    {
        return self::CUSTOM === $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::CUSTOM => $translator->trans('Custom', domain: 'admin', locale: $locale),
            self::IMPORTED => $translator->trans('Imported from Strava', domain: 'admin', locale: $locale),
        };
    }
}

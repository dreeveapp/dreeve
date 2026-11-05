<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityCollectionType: string implements TranslatableInterface
{
    case OTHER = 'other';
    case TRIP = 'trip';
    case EVENT = 'event';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::OTHER => $translator->trans('Other', locale: $locale),
            self::TRIP => $translator->trans('Trip', locale: $locale),
            self::EVENT => $translator->trans('Event', locale: $locale),
        };
    }

    public function getTemplateName(): string
    {
        return match ($this) {
            self::TRIP => 'collection--type--trip',
            self::EVENT => 'collection--type--event',
            default => 'collection--type--generic',
        };
    }
}

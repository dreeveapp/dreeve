<?php

declare(strict_types=1);

namespace App\Domain\Import;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum FileImportStatus: string implements TranslatableInterface
{
    case SUCCESS = 'success';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::SUCCESS => $translator->trans('Success', domain: 'admin', locale: $locale),
            self::SKIPPED => $translator->trans('Skipped', domain: 'admin', locale: $locale),
            self::FAILED => $translator->trans('Failed', domain: 'admin', locale: $locale),
        };
    }
}

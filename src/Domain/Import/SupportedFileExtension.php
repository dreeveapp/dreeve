<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ImportSource;

enum SupportedFileExtension: string
{
    case FIT = 'fit';
    case TCX = 'tcx';
    case GPX = 'gpx';

    public function getImportSource(): ImportSource
    {
        return match ($this) {
            self::FIT => ImportSource::FIT_FILE,
            self::TCX => ImportSource::TCX_FILE,
            self::GPX => ImportSource::GPX_FILE,
        };
    }
}

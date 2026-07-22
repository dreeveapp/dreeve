<?php

declare(strict_types=1);

namespace App\Domain\Activity;

enum ImportSource: string
{
    case STRAVA_API = 'stravaApi';
    case FIT_FILE = 'fitFile';
    case TCX_FILE = 'tcxFile';
    case GPX_FILE = 'gpxFile';

    public function label(): string
    {
        return match ($this) {
            self::STRAVA_API => 'Strava API',
            self::FIT_FILE => 'FIT File',
            self::TCX_FILE => 'TCX File',
            self::GPX_FILE => 'GPX File',
        };
    }

    /**
     * @return ImportSource[]
     */
    public static function fileBasedSources(): array
    {
        return [self::FIT_FILE, self::TCX_FILE, self::GPX_FILE];
    }
}

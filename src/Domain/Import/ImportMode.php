<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum ImportMode: string
{
    case STRAVA_API = 'stravaApi';
    case FILES = 'files';

    public function isStravaApi(): bool
    {
        return self::STRAVA_API === $this;
    }

    public function isFiles(): bool
    {
        return self::FILES === $this;
    }

    public static function fromServerVar(): self
    {
        return self::from($_SERVER['IMPORT_MODE']);
    }
}

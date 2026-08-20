<?php

declare(strict_types=1);

namespace App\Domain\Strava;

final class StravaApplicationIsInactive extends \RuntimeException
{
    public static function create(): self
    {
        return new self('Your Strava API application is inactive, so Dreeve can no longer access the Strava API. Reactivate it on https://www.strava.com/settings/api, or switch to IMPORT_MODE=files');
    }
}

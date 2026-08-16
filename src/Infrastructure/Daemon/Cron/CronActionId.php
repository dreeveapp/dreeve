<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Console\Import\RunStravaImportConsoleCommand;
use App\Console\Notification\AppUpdateAvailableNotificationConsoleCommand;
use App\Console\Notification\GearMaintenanceNotificationConsoleCommand;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Localisation\TranslatableWithDescription;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CronActionId: string implements TranslatableWithDescription
{
    // The backing value is persisted in the daemon settings, renaming it would reset the user's configuration.
    case RUN_STRAVA_IMPORT = 'runStravaImportAndBuildApp';
    case GEAR_MAINTENANCE_NOTIFICATION = 'gearMaintenanceNotification';
    case APP_UPDATE_AVAILABLE_NOTIFICATION = 'appUpdateAvailableNotification';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT => $translator->trans('Import Strava data', domain: 'admin', locale: $locale),
            self::GEAR_MAINTENANCE_NOTIFICATION => $translator->trans('Gear maintenance notification', domain: 'admin', locale: $locale),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => $translator->trans('App update available notification', domain: 'admin', locale: $locale),
        };
    }

    public function transDescription(TranslatorInterface $translator, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT => $translator->trans('Imports new Strava activities.', domain: 'admin', locale: $locale),
            self::GEAR_MAINTENANCE_NOTIFICATION => $translator->trans('Sends a notification when gear maintenance is due. Requires a configured notification service.', domain: 'admin', locale: $locale),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => $translator->trans('Sends a notification when a new app version is available. Requires a configured notification service.', domain: 'admin', locale: $locale),
        };
    }

    public function command(): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT => sprintf('bin/console %s', RunStravaImportConsoleCommand::NAME),
            self::GEAR_MAINTENANCE_NOTIFICATION => sprintf('bin/console %s', GearMaintenanceNotificationConsoleCommand::NAME),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => sprintf('bin/console %s', AppUpdateAvailableNotificationConsoleCommand::NAME),
        };
    }

    public function supportsImportMode(ImportMode $importMode): bool
    {
        if (self::RUN_STRAVA_IMPORT !== $this) {
            return true;
        }

        return !$importMode->isFiles();
    }

    public function defaultCronExpression(): string
    {
        return match ($this) {
            // Deliberately not on the hour: open-meteo has a recurring outage window at 01:00 UTC,
            // which whole-hour European timezones hit at 02:00 local time for part of the year.
            self::RUN_STRAVA_IMPORT => '30 2 * * *',
            self::GEAR_MAINTENANCE_NOTIFICATION, self::APP_UPDATE_AVAILABLE_NOTIFICATION => '0 4 * * *',
        };
    }
}

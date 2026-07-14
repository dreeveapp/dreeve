<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Cron;

use App\Console\Daemon\AppUpdateAvailableNotificationCronAction;
use App\Console\Daemon\GearMaintenanceNotificationConsoleCommand;
use App\Console\Daemon\RunStravaImportAndBuildAppConsoleCommand;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Localisation\TranslatableWithDescription;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CronActionId: string implements TranslatableWithDescription
{
    case RUN_STRAVA_IMPORT_AND_BUILD_APP = 'runStravaImportAndBuildApp';
    case GEAR_MAINTENANCE_NOTIFICATION = 'gearMaintenanceNotification';
    case APP_UPDATE_AVAILABLE_NOTIFICATION = 'appUpdateAvailableNotification';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT_AND_BUILD_APP => $translator->trans('Import data & build app', domain: 'admin', locale: $locale),
            self::GEAR_MAINTENANCE_NOTIFICATION => $translator->trans('Gear maintenance notification', domain: 'admin', locale: $locale),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => $translator->trans('App update available notification', domain: 'admin', locale: $locale),
        };
    }

    public function transDescription(TranslatorInterface $translator, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT_AND_BUILD_APP => $translator->trans('Imports new Strava activities and rebuilds the app.', domain: 'admin', locale: $locale),
            self::GEAR_MAINTENANCE_NOTIFICATION => $translator->trans('Sends a notification when gear maintenance is due. Requires a configured notification service.', domain: 'admin', locale: $locale),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => $translator->trans('Sends a notification when a new app version is available. Requires a configured notification service.', domain: 'admin', locale: $locale),
        };
    }

    public function command(): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT_AND_BUILD_APP => sprintf('bin/console %s', RunStravaImportAndBuildAppConsoleCommand::NAME),
            self::GEAR_MAINTENANCE_NOTIFICATION => sprintf('bin/console %s', GearMaintenanceNotificationConsoleCommand::NAME),
            self::APP_UPDATE_AVAILABLE_NOTIFICATION => sprintf('bin/console %s', AppUpdateAvailableNotificationCronAction::NAME),
        };
    }

    public function supportsImportMode(ImportMode $importMode): bool
    {
        if (self::RUN_STRAVA_IMPORT_AND_BUILD_APP !== $this) {
            return true;
        }

        return !$importMode->isFiles();
    }

    public function defaultCronExpression(): string
    {
        return match ($this) {
            self::RUN_STRAVA_IMPORT_AND_BUILD_APP => '0 2 * * *',
            self::GEAR_MAINTENANCE_NOTIFICATION, self::APP_UPDATE_AVAILABLE_NOTIFICATION => '0 4 * * *',
        };
    }
}

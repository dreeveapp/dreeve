<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Activity\Eddington\Config\EddingtonConfiguration;

enum SettingsName: string
{
    case FIRST_NAME = 'firstName';
    case LAST_NAME = 'lastName';
    case BIRTHDAY = 'birthday';
    case GENDER = 'gender';
    case PROFILE_PICTURE_URL = 'profilePictureUrl';
    case MAX_HEART_RATE_FORMULA = 'maxHeartRateFormula';
    case RESTING_HEART_RATE_FORMULA = 'restingHeartRateFormula';
    case HEART_RATE_ZONES = 'heartRateZones';
    case FTP_HISTORY = 'ftpHistory';
    case WEIGHT_HISTORY = 'weightHistory';
    case APP_SUB_TITLE = 'appSubTitle';
    case UNIT_SYSTEM = 'unitSystem';
    case LOCALE = 'locale';
    case TIME_FORMAT = 'timeFormat';
    case DATE_FORMAT = 'dateFormat';
    case PHOTOS = 'photos';
    case SPORT_TYPES_SORTING_ORDER = 'sportTypesSortingOrder';
    case EDDINGTON = 'eddington';
    case POLYLINE_COLOR = 'polylineColor';
    case ENABLE_GREY_SCALE = 'enableGreyScale';
    case REQUIRE_CTRL_TO_ZOOM = 'requireCtrlToZoom';
    case TILE_LAYER_URL = 'tileLayerUrl';
    case HEATMAP = 'heatmap';
    case NUMBER_OF_NEW_ACTIVITIES_TO_PROCESS_PER_IMPORT = 'numberOfNewActivitiesToProcessPerImport';
    case SPORT_TYPES_TO_IMPORT = 'sportTypesToImport';
    case ACTIVITY_VISIBILITIES_TO_IMPORT = 'activityVisibilitiesToImport';
    case SKIP_ACTIVITIES_RECORDED_BEFORE = 'skipActivitiesRecordedBefore';
    case ACTIVITIES_TO_SKIP_DURING_IMPORT = 'activitiesToSkipDuringImport';
    case OPT_IN_TO_SEGMENT_DETAIL_IMPORT = 'optInToSegmentDetailImport';
    case SKIP_IMAGE_DOWNLOAD_DURING_IMPORT = 'skipImageDownloadDuringImport';
    case WEBHOOKS = 'webhooks';
    case CRON = 'cron';
    case AI = 'ai';
    case NOTIFICATIONS = 'notifications';
    case REQUIRES_AUTHENTICATION = 'requiresAuthentication';
    case VISITORS_CAN_DOWNLOAD_ORIGINAL_FILES = 'visitorsCanDownloadOriginalFiles';
    case ZWIFT_LEVEL = 'level';
    case ZWIFT_RACING_SCORE = 'racingScore';

    public function group(): SettingsGroup
    {
        return match ($this) {
            self::FIRST_NAME, self::LAST_NAME, self::BIRTHDAY, self::GENDER, self::PROFILE_PICTURE_URL,
            self::MAX_HEART_RATE_FORMULA, self::RESTING_HEART_RATE_FORMULA, self::HEART_RATE_ZONES => SettingsGroup::GENERAL,
            self::FTP_HISTORY => SettingsGroup::ATHLETE_FTP,
            self::WEIGHT_HISTORY => SettingsGroup::ATHLETE_WEIGHT,
            self::APP_SUB_TITLE, self::UNIT_SYSTEM, self::LOCALE, self::TIME_FORMAT, self::DATE_FORMAT,
            self::PHOTOS, self::SPORT_TYPES_SORTING_ORDER => SettingsGroup::APPEARANCE,
            self::EDDINGTON => SettingsGroup::METRICS,
            self::POLYLINE_COLOR, self::ENABLE_GREY_SCALE, self::REQUIRE_CTRL_TO_ZOOM, self::TILE_LAYER_URL,
            self::HEATMAP => SettingsGroup::MAPS,
            self::NUMBER_OF_NEW_ACTIVITIES_TO_PROCESS_PER_IMPORT, self::SPORT_TYPES_TO_IMPORT, self::ACTIVITY_VISIBILITIES_TO_IMPORT,
            self::SKIP_ACTIVITIES_RECORDED_BEFORE, self::ACTIVITIES_TO_SKIP_DURING_IMPORT, self::OPT_IN_TO_SEGMENT_DETAIL_IMPORT,
            self::SKIP_IMAGE_DOWNLOAD_DURING_IMPORT, self::WEBHOOKS => SettingsGroup::IMPORT,
            self::CRON => SettingsGroup::DAEMON,
            self::AI, self::NOTIFICATIONS => SettingsGroup::INTEGRATIONS,
            self::REQUIRES_AUTHENTICATION, self::VISITORS_CAN_DOWNLOAD_ORIGINAL_FILES => SettingsGroup::SECURITY,
            self::ZWIFT_LEVEL, self::ZWIFT_RACING_SCORE => SettingsGroup::ZWIFT,
        };
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::MAX_HEART_RATE_FORMULA => 'fox',
            self::RESTING_HEART_RATE_FORMULA => 'heuristicAgeBased',
            self::EDDINGTON => EddingtonConfiguration::getDefaultConfig(),
            default => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum SettingsName: string
{
    case MAX_HEART_RATE_FORMULA = 'maxHeartRateFormula';
    case RESTING_HEART_RATE_FORMULA = 'restingHeartRateFormula';
    case WEIGHT_HISTORY = 'weightHistory';
    case UNIT_SYSTEM = 'unitSystem';
    case LOCALE = 'locale';
    case EDDINGTON = 'eddington';
    case REQUIRES_AUTHENTICATION = 'requiresAuthentication';
}

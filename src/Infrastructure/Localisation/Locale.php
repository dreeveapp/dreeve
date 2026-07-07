<?php

declare(strict_types=1);

namespace App\Infrastructure\Localisation;

enum Locale: string
{
    case de_DE = 'de_DE';
    case en_US = 'en_US';
    case fr_FR = 'fr_FR';
    case hu_HU = 'hu_HU';
    case it_IT = 'it_IT';
    case nl_BE = 'nl_BE';
    case pt_BR = 'pt_BR';
    case pt_PT = 'pt_PT';
    case sv_SE = 'sv_SE';
    case zh_CN = 'zh_CN';
}

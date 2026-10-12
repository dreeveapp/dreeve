<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Installation;

enum InstallationParentType: string
{
    case GEAR = 'gear';
    case COMPONENT = 'component';
}

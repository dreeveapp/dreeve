<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Infrastructure\Eventing\DomainEvent;

final class GearMaintenanceLogWasUpdated extends DomainEvent
{
}

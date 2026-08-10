<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

use App\Infrastructure\Eventing\DomainEvent;

final class DashboardLayoutWasUpdated extends DomainEvent
{
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Infrastructure\Eventing\DomainEvent;

final class ActivityWasDeleted extends DomainEvent
{
}

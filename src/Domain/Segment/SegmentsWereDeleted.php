<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\Eventing\DomainEvent;

final class SegmentsWereDeleted extends DomainEvent
{
}

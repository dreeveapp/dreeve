<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<SegmentId>
 */
final class SegmentIds extends Collection
{
    public function getItemClassName(): string
    {
        return SegmentId::class;
    }
}

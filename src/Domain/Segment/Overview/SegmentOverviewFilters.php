<?php

declare(strict_types=1);

namespace App\Domain\Segment\Overview;

use App\Domain\Segment\SegmentType;
use App\Infrastructure\Http\Request\Filters;

final readonly class SegmentOverviewFilters extends Filters
{
    public function isEmpty(): bool
    {
        return null === $this->getName()
            && !$this->getType() instanceof SegmentType;
    }

    public function getName(): ?string
    {
        $name = trim($this->getString('name') ?? '');

        return '' === $name ? null : $name;
    }

    public function getType(): ?SegmentType
    {
        if (null === $type = $this->getString('type')) {
            return null;
        }

        return SegmentType::tryFrom($type);
    }
}

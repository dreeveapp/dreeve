<?php

declare(strict_types=1);

namespace App\Domain\Segment\Overview;

use App\Domain\Segment\SegmentType;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface SegmentOverviewRepository
{
    /**
     * @return Overview<SegmentOverviewItem>
     */
    public function find(
        Pagination $pagination,
        SegmentOverviewFilters $filters,
    ): Overview;

    public function countByType(SegmentType $type): int;
}

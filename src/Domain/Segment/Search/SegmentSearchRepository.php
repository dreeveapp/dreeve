<?php

declare(strict_types=1);

namespace App\Domain\Segment\Search;

use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface SegmentSearchRepository
{
    /**
     * @return Overview<SegmentSearchResult>
     */
    public function find(
        Pagination $pagination,
        SegmentSearchFilters $filters,
    ): Overview;
}

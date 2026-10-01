<?php

declare(strict_types=1);

namespace App\Domain\Activity\Search;

use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface ActivitySearchRepository
{
    /**
     * @return Overview<ActivitySearchResult>
     */
    public function find(
        Pagination $pagination,
        ActivitySearchFilters $filters,
    ): Overview;
}

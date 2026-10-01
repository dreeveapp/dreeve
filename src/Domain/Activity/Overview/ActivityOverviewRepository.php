<?php

declare(strict_types=1);

namespace App\Domain\Activity\Overview;

use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface ActivityOverviewRepository
{
    /**
     * @return Overview<ActivityOverviewItem>
     */
    public function find(
        Pagination $pagination,
        ActivityOverviewFilters $filters,
    ): Overview;

    /**
     * @return list<ActivityOverviewItem>
     */
    public function search(
        string $query,
        int $limit,
    ): array;
}

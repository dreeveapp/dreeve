<?php

declare(strict_types=1);

namespace App\Domain\Import\Search;

use App\Domain\Import\Overview\FileImportOverviewItem;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface FileImportSearchRepository
{
    /**
     * @return Overview<FileImportOverviewItem>
     */
    public function find(
        Pagination $pagination,
        FileImportSearchFilters $filters,
    ): Overview;
}

<?php

declare(strict_types=1);

namespace App\Domain\Import\Search;

use App\Controller\Api\V1\FileImport\FileImportSearchFilters;
use App\Domain\Import\FileImportOverviewItem;
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

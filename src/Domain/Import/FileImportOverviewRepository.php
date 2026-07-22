<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Controller\Admin\File\FileImportOverviewFilters;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

interface FileImportOverviewRepository
{
    /**
     * @return Overview<FileImportOverviewItem>
     */
    public function find(
        Pagination $pagination,
        FileImportOverviewFilters $filters,
    ): Overview;

    public function findOneByFileImportId(FileImportId $fileImportId): FileImportOverviewItem;
}

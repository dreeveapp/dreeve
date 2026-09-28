<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;

final readonly class QueuedFirstOverview
{
    /**
     * @param list<FileImportOverviewItem>                                    $queued
     * @param \Closure(int $offset, int $limit): list<FileImportOverviewItem> $fetchImported
     *
     * @return Overview<FileImportOverviewItem>
     */
    public static function create(
        array $queued,
        int $totalImported,
        Pagination $pagination,
        \Closure $fetchImported,
    ): Overview {
        $items = array_slice($queued, $pagination->getOffset(), $pagination->getLimit());

        if (($limit = $pagination->getLimit() - count($items)) > 0) {
            $items = [
                ...$items,
                ...$fetchImported(max(0, $pagination->getOffset() - count($queued)), $limit),
            ];
        }

        return Overview::create(
            pagination: $pagination,
            total: count($queued) + $totalImported,
            items: $items,
        );
    }
}

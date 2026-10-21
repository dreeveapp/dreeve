<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Infrastructure\Repository\Pagination;

trait ProvidePagination
{
    /**
     * @return array{page: int, size: int, total: int, totalPages: int}
     */
    private static function buildPagination(Pagination $pagination, int $total): array
    {
        return [
            'page' => $pagination->getCurrentPage(),
            'size' => $pagination->getLimit(),
            'total' => $total,
            'totalPages' => (int) ceil($total / $pagination->getLimit()),
        ];
    }
}

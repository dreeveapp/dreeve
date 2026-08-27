<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Infrastructure\ValueObject\Collection;

/**
 * @extends Collection<FileImportId>
 */
final class FileImportIds extends Collection
{
    public function getItemClassName(): string
    {
        return FileImportId::class;
    }
}

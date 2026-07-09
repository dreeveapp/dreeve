<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum FileImportStatus: string
{
    case SUCCESS = 'success';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
}

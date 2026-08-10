<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Import\WatchDirectory;

final readonly class ImportStatus
{
    public function __construct(
        private WatchDirectory $watchDirectory,
    ) {
    }

    public function isPending(): bool
    {
        return $this->watchDirectory->hasFilesThatCanBeProcessed();
    }
}

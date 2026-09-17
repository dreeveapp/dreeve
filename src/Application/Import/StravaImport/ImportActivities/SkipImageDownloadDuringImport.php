<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportActivities;

final readonly class SkipImageDownloadDuringImport
{
    private function __construct(
        private bool $flag,
    ) {
    }

    public static function fromBool(bool $flag): self
    {
        return new self($flag);
    }

    public function shouldSkip(): bool
    {
        return $this->flag;
    }
}

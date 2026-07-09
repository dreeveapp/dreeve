<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Domain\Import\FileParser\RawActivityFile;

final class SkipDuplicateActivity extends \RuntimeException
{
    public function __construct(
        private readonly RawActivityFile $activityFile,
    ) {
        parent::__construct();
    }

    public function getActivityFile(): RawActivityFile
    {
        return $this->activityFile;
    }
}

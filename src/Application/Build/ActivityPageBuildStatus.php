<?php

declare(strict_types=1);

namespace App\Application\Build;

use App\Domain\Activity\ActivityId;
use League\Flysystem\FilesystemOperator;

final readonly class ActivityPageBuildStatus
{
    public function __construct(
        private FilesystemOperator $buildHtmlStorage,
    ) {
    }

    public function hasBeenBuilt(ActivityId $activityId): bool
    {
        return $this->buildHtmlStorage->fileExists('activity/'.$activityId.'.html');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Infrastructure\Eventing\DomainEvent;

final class FileImportWasDeleted extends DomainEvent
{
    public function __construct(
        private readonly FileImportId $fileImportId,
        private readonly ?ActivityId $activityId,
    ) {
    }

    public function getFileImportId(): FileImportId
    {
        return $this->fileImportId;
    }

    public function getActivityId(): ?ActivityId
    {
        return $this->activityId;
    }
}

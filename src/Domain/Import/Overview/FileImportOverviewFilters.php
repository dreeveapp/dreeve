<?php

declare(strict_types=1);

namespace App\Domain\Import\Overview;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Http\Request\Filters;

final readonly class FileImportOverviewFilters extends Filters
{
    public function isEmpty(): bool
    {
        return !$this->getStatus() instanceof FileImportStatus
            && !$this->getSource() instanceof ImportSource
            && null === $this->getFilename()
            && !$this->getActivityId() instanceof ActivityId;
    }

    public function getStatus(): ?FileImportStatus
    {
        if (null === $status = $this->getString('status')) {
            return null;
        }

        return FileImportStatus::tryFrom($status);
    }

    public function getSource(): ?ImportSource
    {
        if (null === $source = $this->getString('source')) {
            return null;
        }

        return ImportSource::tryFrom($source);
    }

    public function getFilename(): ?string
    {
        $filename = trim($this->getString('filename') ?? '');

        return '' === $filename ? null : $filename;
    }

    public function getActivityId(): ?ActivityId
    {
        $activityId = trim($this->getString('activity') ?? '');
        if (!str_starts_with($activityId, ActivityId::getPrefix())) {
            return null;
        }

        return ActivityId::fromString($activityId);
    }
}

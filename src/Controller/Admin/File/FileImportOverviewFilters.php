<?php

declare(strict_types=1);

namespace App\Controller\Admin\File;

use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Http\Request\Filters;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class FileImportOverviewFilters extends Filters
{
    public function isEmpty(): bool
    {
        return !$this->getStatus() instanceof FileImportStatus && !$this->getSource() instanceof ImportSource;
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
}

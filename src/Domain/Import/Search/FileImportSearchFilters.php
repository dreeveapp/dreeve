<?php

declare(strict_types=1);

namespace App\Domain\Import\Search;

use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Http\Request\Filters;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class FileImportSearchFilters extends Filters
{
    public function getFilename(): ?string
    {
        return $this->getString('filename');
    }

    /**
     * @return list<FileImportStatus>
     */
    public function getStatuses(): array
    {
        return array_map(
            static function (string $value): FileImportStatus {
                $status = FileImportStatus::tryFrom($value);
                if (!$status instanceof FileImportStatus || FileImportStatus::QUEUED === $status) {
                    throw new BadRequestHttpException('"filters[status]" contains an unknown status.');
                }

                return $status;
            },
            $this->getList('status')
        );
    }

    /**
     * @return list<ImportSource>
     */
    public function getSources(): array
    {
        return array_map(
            static function (string $value): ImportSource {
                $source = ImportSource::tryFrom($value);
                if (!$source instanceof ImportSource || !in_array($source, ImportSource::fileBasedSources(), true)) {
                    throw new BadRequestHttpException('"filters[source]" contains an unknown source.');
                }

                return $source;
            },
            $this->getList('source')
        );
    }

    /**
     * @return list<string>
     */
    private function getList(string $name): array
    {
        if (null === $value = $this->getString($name)) {
            return [];
        }

        return array_map(trim(...), explode(',', $value));
    }
}

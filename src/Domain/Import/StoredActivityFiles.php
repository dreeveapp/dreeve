<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileParser\ActivityFileParsers;
use App\Domain\Import\FileParser\ParsedActivityFile;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\String\Path;

final readonly class StoredActivityFiles
{
    public function __construct(
        private FileImportRepository $fileImportRepository,
        private ActivityFileParsers $activityFileParsers,
    ) {
    }

    /**
     * @param \Closure(string): void $reportFailure
     *
     * @return iterable<ActivityId, ParsedActivityFile>
     */
    public function parse(\Closure $reportFailure): iterable
    {
        foreach ($this->fileImportRepository->findIdsWithFileContents() as $fileImportId) {
            try {
                $fileImport = $this->fileImportRepository->find($fileImportId);
            } catch (\Throwable $e) {
                $reportFailure(sprintf('%s: %s', $fileImportId, $e->getMessage()));

                continue;
            }

            $activityId = $fileImport->getActivityId();
            $fileContents = $fileImport->getFileContents();
            assert($activityId instanceof ActivityId);
            assert(null !== $fileContents);

            try {
                $parsedActivityFile = $this->activityFileParsers->parse(RawActivityFile::from(
                    filePath: Path::fromString(basename($fileImport->getOriginalFilename())),
                    fileContents: $fileContents,
                ));
            } catch (\Throwable $e) {
                $reportFailure(sprintf('%s (%s): %s', $activityId, $fileImport->getOriginalFilename(), $e->getMessage()));

                continue;
            }

            yield $activityId => $parsedActivityFile;
        }
    }
}

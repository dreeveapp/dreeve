<?php

declare(strict_types=1);

namespace App\Domain\Import\Search;

use App\Controller\Api\V1\FileImport\FileImportSearchFilters;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportOverviewItem;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\SupportedFileExtension;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\StorageAttributes;

final readonly class DbalFileImportSearchRepository extends DbalRepository implements FileImportSearchRepository
{
    public function __construct(
        Connection $connection,
        private WatchDirectory $watchDirectory,
    ) {
        parent::__construct($connection);
    }

    public function find(Pagination $pagination, FileImportSearchFilters $filters): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('fi.fileImportId', 'fi.originalFilename', 'fi.source', 'fi.status', 'fi.errorMessage', 'fi.activityId', 'fi.importedOn', 'fi.fileContents IS NOT NULL AS hasFileContents', 'a.name AS activityName')
            ->from('FileImport', 'fi')
            ->leftJoin('fi', 'Activity', 'a', 'a.activityId = fi.activityId')
            ->orderBy('fi.importedOn', 'DESC');

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('FileImport', 'fi');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if (null !== $filename = $filters->getFilename()) {
                $builder
                    ->andWhere('fi.originalFilename = :filename')
                    ->setParameter('filename', $filename);
            }
            if ([] !== $statuses = $filters->getStatuses()) {
                $builder
                    ->andWhere('fi.status IN (:statuses)')
                    ->setParameter(
                        key: 'statuses',
                        value: array_map(static fn (FileImportStatus $status): string => $status->value, $statuses),
                        type: ArrayParameterType::STRING
                    );
            }
            if ([] !== $sources = $filters->getSources()) {
                $builder
                    ->andWhere('fi.source IN (:sources)')
                    ->setParameter(
                        key: 'sources',
                        value: array_map(static fn (ImportSource $source): string => $source->value, $sources),
                        type: ArrayParameterType::STRING
                    );
            }
        }

        $queued = $this->watchDirectory->listFilesThatCanBeProcessed()
            ->map(static function (StorageAttributes $file): FileImportOverviewItem {
                $path = Path::fromString($file->path());

                return FileImportOverviewItem::queued(
                    originalFilename: $path->getFilename(),
                    source: SupportedFileExtension::from($path->getExtension())->getImportSource(),
                );
            })
            ->toArray();

        $items = array_values(array_slice($queued, $pagination->getOffset(), $pagination->getLimit()));
        if (($limit = $pagination->getLimit() - count($items)) > 0) {
            $results = $queryBuilder
                ->setFirstResult(max(0, $pagination->getOffset() - count($queued)))
                ->setMaxResults($limit)
                ->executeQuery()
                ->fetchAllAssociative();

            $items = [...$items, ...array_map($this->hydrate(...), $results)];
        }

        return Overview::create(
            pagination: $pagination,
            total: count($queued) + (int) $countQueryBuilder->executeQuery()->fetchOne(),
            items: $items,
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): FileImportOverviewItem
    {
        return FileImportOverviewItem::fromState(
            fileImportId: FileImportId::fromString($result['fileImportId']),
            originalFilename: $result['originalFilename'],
            source: ImportSource::from($result['source']),
            status: FileImportStatus::from($result['status']),
            importedOn: SerializableDateTime::fromString($result['importedOn']),
            errorMessage: $result['errorMessage'],
            activityId: ActivityId::fromOptionalString($result['activityId']),
            activityName: $result['activityName'],
            hasFileContents: (bool) $result['hasFileContents'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Controller\Admin\File\FileImportOverviewFilters;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;
use League\Flysystem\StorageAttributes;

final readonly class DbalFileImportOverviewRepository extends DbalRepository implements FileImportOverviewRepository
{
    public function __construct(
        Connection $connection,
        private WatchDirectory $watchDirectory,
    ) {
        parent::__construct($connection);
    }

    public function find(Pagination $pagination, FileImportOverviewFilters $filters): Overview
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
            if (($status = $filters->getStatus()) instanceof FileImportStatus) {
                $builder
                    ->andWhere('fi.status = :status')
                    ->setParameter('status', $status->value);
            }
            if (($source = $filters->getSource()) instanceof ImportSource) {
                $builder
                    ->andWhere('fi.source = :source')
                    ->setParameter('source', $source->value);
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

    public function findOneByFileImportId(FileImportId $fileImportId): FileImportOverviewItem
    {
        $result = $this->connection->createQueryBuilder()
            ->select('fi.fileImportId', 'fi.originalFilename', 'fi.source', 'fi.status', 'fi.errorMessage', 'fi.activityId', 'fi.importedOn', 'fi.fileContents IS NOT NULL AS hasFileContents', 'a.name AS activityName')
            ->from('FileImport', 'fi')
            ->leftJoin('fi', 'Activity', 'a', 'a.activityId = fi.activityId')
            ->andWhere('fi.fileImportId = :fileImportId')
            ->setParameter('fileImportId', (string) $fileImportId)
            ->executeQuery()
            ->fetchAssociative();

        if (false === $result) {
            throw new EntityNotFound(sprintf('File import "%s" is no longer available', $fileImportId));
        }

        return $this->hydrate($result);
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

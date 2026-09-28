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
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalFileImportOverviewRepository extends DbalRepository implements FileImportOverviewRepository
{
    public function __construct(
        Connection $connection,
        private QueuedFileImports $queuedFileImports,
    ) {
        parent::__construct($connection);
    }

    public function find(Pagination $pagination, FileImportOverviewFilters $filters): Overview
    {
        $status = $filters->getStatus();
        $source = $filters->getSource();

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('fi.fileImportId', 'fi.originalFilename', 'fi.source', 'fi.status', 'fi.errorMessage', 'fi.activityId', 'fi.importedOn', 'fi.fileContents IS NOT NULL AS hasFileContents', 'a.name AS activityName')
            ->from('FileImport', 'fi')
            ->leftJoin('fi', 'Activity', 'a', 'a.activityId = fi.activityId')
            ->orderBy('fi.importedOn', 'DESC');

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('FileImport', 'fi');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if ($status instanceof FileImportStatus) {
                $builder
                    ->andWhere('fi.status = :status')
                    ->setParameter('status', $status->value);
            }
            if ($source instanceof ImportSource) {
                $builder
                    ->andWhere('fi.source = :source')
                    ->setParameter('source', $source->value);
            }
        }

        return QueuedFirstOverview::create(
            queued: in_array($status, [null, FileImportStatus::QUEUED], true)
                ? $this->queuedFileImports->find(source: $source)
                : [],
            totalImported: (int) $countQueryBuilder->executeQuery()->fetchOne(),
            pagination: $pagination,
            fetchImported: fn (int $offset, int $limit): array => array_map(
                $this->hydrate(...),
                $queryBuilder
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->executeQuery()
                    ->fetchAllAssociative()
            ),
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

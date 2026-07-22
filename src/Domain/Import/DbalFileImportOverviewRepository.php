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

final readonly class DbalFileImportOverviewRepository extends DbalRepository implements FileImportOverviewRepository
{
    public function find(Pagination $pagination, FileImportOverviewFilters $filters): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('fi.fileImportId', 'fi.originalFilename', 'fi.source', 'fi.status', 'fi.errorMessage', 'fi.activityId', 'fi.importedOn', 'a.name AS activityName')
            ->from('FileImport', 'fi')
            ->leftJoin('fi', 'Activity', 'a', 'a.activityId = fi.activityId')
            ->orderBy('fi.importedOn', 'DESC')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit());

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

        $results = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();

        $total = (int) $countQueryBuilder
            ->executeQuery()
            ->fetchOne();

        return Overview::create(
            pagination: $pagination,
            total: $total,
            items: array_map($this->hydrate(...), $results),
        );
    }

    public function findOneByFileImportId(FileImportId $fileImportId): FileImportOverviewItem
    {
        $result = $this->connection->createQueryBuilder()
            ->select('fi.fileImportId', 'fi.originalFilename', 'fi.source', 'fi.status', 'fi.errorMessage', 'fi.activityId', 'fi.importedOn', 'a.name AS activityName')
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
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Gear\Maintenance\Log;

use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearComponents;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalGearMaintenanceLogOverviewRepository implements GearMaintenanceLogOverviewRepository
{
    public function __construct(
        private Connection $connection,
        private GearComponentRepository $gearComponentRepository,
    ) {
    }

    public function find(Pagination $pagination): Overview
    {
        $labelsByTaskId = $this->resolveLabelsByTaskId($this->gearComponentRepository->findAll());
        if ([] === $labelsByTaskId) {
            return Overview::create(pagination: $pagination, total: 0, items: []);
        }

        $validTaskIds = array_keys($labelsByTaskId);

        $results = $this->connection->createQueryBuilder()
            ->select('gml.gearMaintenanceLogId', 'gml.maintenanceTaskId', 'gml.performedOn', 'g.name AS gearName')
            ->from('GearMaintenanceLog', 'gml')
            ->innerJoin('gml', 'Gear', 'g', 'g.gearId = gml.gearId')
            ->andWhere('gml.maintenanceTaskId IN (:maintenanceTaskIds)')
            ->setParameter('maintenanceTaskIds', $validTaskIds, ArrayParameterType::STRING)
            ->orderBy('gml.performedOn', 'DESC')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit())
            ->executeQuery()
            ->fetchAllAssociative();

        $total = (int) $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('GearMaintenanceLog', 'gml')
            ->innerJoin('gml', 'Gear', 'g', 'g.gearId = gml.gearId')
            ->andWhere('gml.maintenanceTaskId IN (:maintenanceTaskIds)')
            ->setParameter('maintenanceTaskIds', $validTaskIds, ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchOne();

        return Overview::create(
            pagination: $pagination,
            total: $total,
            items: array_map(
                fn (array $result): GearMaintenanceLogOverviewItem => $this->hydrate($result, $labelsByTaskId),
                $results
            ),
        );
    }

    public function findOneByGearMaintenanceLogId(GearMaintenanceLogId $gearMaintenanceLogId): GearMaintenanceLogOverviewItem
    {
        $labelsByTaskId = $this->resolveLabelsByTaskId($this->gearComponentRepository->findAll());

        $result = false;
        if ([] !== $labelsByTaskId) {
            $result = $this->connection->createQueryBuilder()
                ->select('gml.gearMaintenanceLogId', 'gml.maintenanceTaskId', 'gml.performedOn', 'g.name AS gearName')
                ->from('GearMaintenanceLog', 'gml')
                ->innerJoin('gml', 'Gear', 'g', 'g.gearId = gml.gearId')
                ->andWhere('gml.gearMaintenanceLogId = :gearMaintenanceLogId')
                ->andWhere('gml.maintenanceTaskId IN (:maintenanceTaskIds)')
                ->setParameter('gearMaintenanceLogId', (string) $gearMaintenanceLogId)
                ->setParameter('maintenanceTaskIds', array_keys($labelsByTaskId), ArrayParameterType::STRING)
                ->executeQuery()
                ->fetchAssociative();
        }

        if (false === $result) {
            throw new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $gearMaintenanceLogId));
        }

        return $this->hydrate($result, $labelsByTaskId);
    }

    /**
     * @return array<string, array{component: string, task: string}>
     */
    private function resolveLabelsByTaskId(GearComponents $gearComponents): array
    {
        $labelsByTaskId = [];
        foreach ($gearComponents as $gearComponent) {
            foreach ($gearComponent->getMaintenanceTasks() as $maintenanceTask) {
                $labelsByTaskId[(string) $maintenanceTask->getId()] = [
                    'component' => (string) $gearComponent->getLabel(),
                    'task' => (string) $maintenanceTask->getLabel(),
                ];
            }
        }

        return $labelsByTaskId;
    }

    /**
     * @param array<string, mixed>                                  $result
     * @param array<string, array{component: string, task: string}> $labelsByTaskId
     */
    private function hydrate(array $result, array $labelsByTaskId): GearMaintenanceLogOverviewItem
    {
        $labels = $labelsByTaskId[$result['maintenanceTaskId']];

        return GearMaintenanceLogOverviewItem::fromState(
            gearMaintenanceLogId: GearMaintenanceLogId::fromString($result['gearMaintenanceLogId']),
            gearName: (string) $result['gearName'],
            componentLabel: $labels['component'],
            taskLabel: $labels['task'],
            performedOn: SerializableDateTime::fromString($result['performedOn']),
        );
    }
}

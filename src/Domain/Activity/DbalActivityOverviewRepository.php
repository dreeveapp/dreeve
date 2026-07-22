<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Controller\Admin\Activity\ActivityOverviewFilters;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalActivityOverviewRepository extends DbalRepository implements ActivityOverviewRepository
{
    public function find(Pagination $pagination, ActivityOverviewFilters $filters): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                'a.activityId',
                'a.name',
                'a.sportType',
                'a.startDateTime',
                'a.deviceName',
                'a.isCommute',
                'a.totalImageCount',
                'g.name AS gearName',
            )
            ->from('Activity', 'a')
            ->leftJoin('a', 'Gear', 'g', 'a.gearId = g.gearId')
            ->orderBy('a.startDateTime', 'DESC')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit());

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Activity', 'a');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if (($sportType = $filters->getSportType()) instanceof SportType) {
                $builder
                    ->andWhere('a.sportType = :sportType')
                    ->setParameter('sportType', $sportType->value);
            }
            if (($gearId = $filters->getGearId()) instanceof GearId) {
                $builder
                    ->andWhere('a.gearId = :gearId')
                    ->setParameter('gearId', (string) $gearId);
            }
            if (null !== $device = $filters->getDevice()) {
                $builder
                    ->andWhere('a.deviceName = :deviceName')
                    ->setParameter('deviceName', $device);
            }
            if (($importSource = $filters->getImportSource()) instanceof ImportSource) {
                $builder
                    ->andWhere('a.importSource = :importSource')
                    ->setParameter('importSource', $importSource->value);
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

    public function search(string $query, int $limit): array
    {
        $tokens = array_filter(preg_split('/\s+/', trim($query)) ?: []);
        if ([] === $tokens) {
            return [];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                'a.activityId',
                'a.name',
                'a.sportType',
                'a.startDateTime',
                'a.deviceName',
                'a.isCommute',
                'a.totalImageCount',
                'g.name AS gearName',
            )
            ->from('Activity', 'a')
            ->leftJoin('a', 'Gear', 'g', 'a.gearId = g.gearId')
            ->orderBy('a.startDateTime', 'DESC')
            ->setMaxResults($limit);

        foreach (array_values($tokens) as $index => $token) {
            $parameter = 'token'.$index;
            $queryBuilder->andWhere(sprintf(
                '(a.name LIKE :%1$s OR a.activityId LIKE :%1$s OR a.startDateTime LIKE :%1$s OR a.sportType LIKE :%1$s)',
                $parameter
            ));
            $queryBuilder->setParameter($parameter, '%'.$token.'%');
        }

        return array_map(
            $this->hydrate(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityOverviewItem
    {
        $startDate = SerializableDateTime::fromString($result['startDateTime']);
        $sportType = SportType::from($result['sportType']);

        return ActivityOverviewItem::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            name: '' !== trim((string) $result['name'])
                ? ActivityName::fromString($result['name'])
                : ActivityName::from($startDate, $sportType),
            sportType: $sportType,
            startDate: $startDate,
            gearName: $result['gearName'] ?? null,
            deviceName: $result['deviceName'] ?? null,
            isCommute: (bool) ($result['isCommute'] ?? false),
            totalImageCount: (int) ($result['totalImageCount'] ?? 0),
        );
    }
}

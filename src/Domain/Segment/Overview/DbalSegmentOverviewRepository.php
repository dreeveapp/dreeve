<?php

declare(strict_types=1);

namespace App\Domain\Segment\Overview;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\String\Name;

final readonly class DbalSegmentOverviewRepository extends DbalRepository implements SegmentOverviewRepository
{
    public function find(Pagination $pagination, SegmentOverviewFilters $filters): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('s.segmentId', 's.name', 's.type', 's.sportType', 's.distance', 's.averageGradient', 'COUNT(e.segmentEffortId) AS numberOfEfforts')
            ->from('Segment', 's')
            ->leftJoin('s', 'SegmentEffort', 'e', 'e.segmentId = s.segmentId')
            ->groupBy('s.segmentId')
            ->orderBy('CASE WHEN s.type = :customType THEN 0 ELSE 1 END')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('s.segmentId', 'ASC')
            ->setParameter('customType', SegmentType::CUSTOM->value)
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit());

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Segment', 's');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if (($type = $filters->getType()) instanceof SegmentType) {
                $builder
                    ->andWhere('s.type = :type')
                    ->setParameter('type', $type->value);
            }
            if (null !== $name = $filters->getName()) {
                $builder
                    ->andWhere("s.name LIKE :name ESCAPE '\\'")
                    ->setParameter('name', '%'.addcslashes($name, '%_\\').'%');
            }
        }

        return Overview::create(
            pagination: $pagination,
            total: (int) $countQueryBuilder->executeQuery()->fetchOne(),
            items: array_map(
                static fn (array $result): SegmentOverviewItem => SegmentOverviewItem::fromState(
                    segmentId: SegmentId::fromString($result['segmentId']),
                    name: Name::fromString($result['name']),
                    type: SegmentType::from($result['type']),
                    sportType: SportType::from($result['sportType']),
                    distance: Meter::from($result['distance'])->toKilometer(),
                    averageGradient: isset($result['averageGradient']) ? (float) $result['averageGradient'] : null,
                    numberOfEfforts: (int) $result['numberOfEfforts'],
                ),
                $queryBuilder->executeQuery()->fetchAllAssociative(),
            ),
        );
    }

    public function countByType(SegmentType $type): int
    {
        return (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM Segment WHERE type = :type',
            ['type' => $type->value]
        )->fetchOne();
    }
}

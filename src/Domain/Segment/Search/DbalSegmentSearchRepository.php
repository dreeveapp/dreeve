<?php

declare(strict_types=1);

namespace App\Domain\Segment\Search;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentHydrator;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalSegmentSearchRepository extends DbalRepository implements SegmentSearchRepository
{
    private const string BEST_EFFORTS = '(
        SELECT SegmentEffort.*,
            ROW_NUMBER() OVER (
                PARTITION BY segmentId
                ORDER BY elapsedTimeInSeconds ASC, segmentEffortId ASC
            ) AS rank,
            COUNT(*) OVER (PARTITION BY segmentId) AS numberOfTimesRidden
        FROM SegmentEffort
    )';

    public function find(Pagination $pagination, SegmentSearchFilters $filters): Overview
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select(
                SegmentHydrator::columns('s'),
                'COALESCE(e.numberOfTimesRidden, 0) AS numberOfTimesRidden',
                'e.segmentEffortId AS bestEffortId',
                'e.activityId AS bestEffortActivityId',
                'e.startDateTime AS bestEffortStartDateTime',
                'e.elapsedTimeInSeconds AS bestEffortElapsedTimeInSeconds',
                'e.distance AS bestEffortDistance',
                'e.averageWatts AS bestEffortAverageWatts',
                'e.averageHeartRate AS bestEffortAverageHeartRate',
                'e.maxHeartRate AS bestEffortMaxHeartRate',
            )
            ->from('Segment', 's')
            ->leftJoin('s', self::BEST_EFFORTS, 'e', 'e.segmentId = s.segmentId AND e.rank = 1')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('s.segmentId', 'ASC')
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit());

        $countQueryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('Segment', 's');

        foreach ([$queryBuilder, $countQueryBuilder] as $builder) {
            if (!$filters->getSportTypes()->isEmpty()) {
                $builder
                    ->andWhere('s.sportType IN (:sportTypes)')
                    ->setParameter(
                        key: 'sportTypes',
                        value: $filters->getSportTypes()->toValues(),
                        type: ArrayParameterType::STRING
                    );
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

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): SegmentSearchResult
    {
        return SegmentSearchResult::fromState(
            segment: SegmentHydrator::hydrate($result),
            numberOfTimesRidden: (int) $result['numberOfTimesRidden'],
            bestEffort: null === $result['bestEffortId'] ? null : SegmentEffort::fromState(
                segmentEffortId: SegmentEffortId::fromString($result['bestEffortId']),
                segmentId: SegmentId::fromString($result['segmentId']),
                activityId: ActivityId::fromString($result['bestEffortActivityId']),
                startDateTime: SerializableDateTime::fromString($result['bestEffortStartDateTime']),
                name: $result['name'],
                elapsedTimeInSeconds: $result['bestEffortElapsedTimeInSeconds'],
                distance: Meter::from($result['bestEffortDistance'])->toKilometer(),
                averageWatts: $result['bestEffortAverageWatts'],
                averageHeartRate: $result['bestEffortAverageHeartRate'],
                maxHeartRate: $result['bestEffortMaxHeartRate'],
                rank: 1,
            ),
        );
    }
}

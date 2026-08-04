<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class DbalSegmentEffortRepository extends DbalRepository implements SegmentEffortRepository
{
    public function __construct(
        Connection $connection,
        private SegmentEffortRankingMap $segmentEffortRankingMap,
    ) {
        parent::__construct($connection);
    }

    public function add(SegmentEffort $segmentEffort): void
    {
        $sql = 'INSERT INTO SegmentEffort (segmentEffortId, segmentId, activityId, startDateTime, 
                           name, elapsedTimeInSeconds, distance, averageWatts, averageHeartRate, maxHeartRate)
                VALUES (:segmentEffortId, :segmentId, :activityId, :startDateTime, 
                        :name, :elapsedTimeInSeconds, :distance, :averageWatts, :averageHeartRate, :maxHeartRate)';

        $this->connection->executeStatement($sql, [
            'segmentEffortId' => $segmentEffort->getId(),
            'segmentId' => $segmentEffort->getSegmentId(),
            'activityId' => $segmentEffort->getActivityId(),
            'startDateTime' => $segmentEffort->getStartDateTime(),
            'name' => $segmentEffort->getName(),
            'elapsedTimeInSeconds' => $segmentEffort->getElapsedTimeInSeconds(),
            'distance' => $segmentEffort->getDistance()->toMeter()->toInt(),
            'averageWatts' => $segmentEffort->getAverageWatts(),
            'averageHeartRate' => $segmentEffort->getAverageHeartRate(),
            'maxHeartRate' => $segmentEffort->getMaxHeartRate(),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM SegmentEffort 
        WHERE activityId = :activityId';

        $this->connection->executeStatement($sql,
            [
                'activityId' => $activityId,
            ]
        );
    }

    public function find(SegmentEffortId $segmentEffortId): SegmentEffort
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('SegmentEffort')
            ->andWhere('segmentEffortId = :segmentEffortId')
            ->setParameter('segmentEffortId', $segmentEffortId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('segmentEffort "%s" not found', $segmentEffortId));
        }

        return $this->hydrateWithRankingMap($result);
    }

    public function findTopXBySegmentId(SegmentId $segmentId, int $limit): SegmentEfforts
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('SegmentEffort')
            ->andWhere('segmentId = :segmentId')
            ->setParameter('segmentId', $segmentId)
            ->setMaxResults($limit)
            ->orderBy('elapsedTimeInSeconds', 'ASC');

        return SegmentEfforts::fromArray(array_map(
            $this->hydrateWithRankingMap(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    public function findBySegmentId(SegmentId $segmentId): SegmentEfforts
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('SegmentEffort')
            ->andWhere('segmentId = :segmentId')
            ->setParameter('segmentId', $segmentId)
            ->orderBy('startDateTime', 'DESC');

        return SegmentEfforts::fromArray(array_map(
            $this->hydrateWithRankingMap(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    public function countBySegmentId(SegmentId $segmentId): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*)')
            ->from('SegmentEffort')
            ->andWhere('segmentId = :segmentId')
            ->setParameter('segmentId', $segmentId);

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function findByActivityId(ActivityId $activityId): SegmentEfforts
    {
        $sql = 'SELECT * FROM (
                    SELECT SegmentEffort.*, ROW_NUMBER() OVER (
                        PARTITION BY segmentId
                        ORDER BY elapsedTimeInSeconds ASC, segmentEffortId ASC
                    ) rank
                    FROM SegmentEffort
                    WHERE segmentId IN (SELECT segmentId FROM SegmentEffort WHERE activityId = :activityId)
                ) ranked
                WHERE activityId = :activityId
                ORDER BY startDateTime ASC, segmentEffortId ASC';

        return SegmentEfforts::fromArray(array_map(
            fn (array $result): SegmentEffort => $this->hydrate($result, (int) $result['rank']),
            $this->connection->executeQuery($sql, ['activityId' => (string) $activityId])->fetchAllAssociative()
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateWithRankingMap(array $result): SegmentEffort
    {
        return $this->hydrate(
            $result,
            $this->segmentEffortRankingMap->getRankFor(SegmentEffortId::fromString($result['segmentEffortId']))
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result, ?int $rank): SegmentEffort
    {
        return SegmentEffort::fromState(
            segmentEffortId: SegmentEffortId::fromString($result['segmentEffortId']),
            segmentId: SegmentId::fromString($result['segmentId']),
            activityId: ActivityId::fromString($result['activityId']),
            startDateTime: SerializableDateTime::fromString($result['startDateTime']),
            name: $result['name'],
            elapsedTimeInSeconds: $result['elapsedTimeInSeconds'],
            distance: Meter::from($result['distance'])->toKilometer(),
            averageWatts: $result['averageWatts'],
            averageHeartRate: $result['averageHeartRate'],
            maxHeartRate: $result['maxHeartRate'],
            rank: $rank,
        );
    }
}

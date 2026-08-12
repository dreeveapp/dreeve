<?php

declare(strict_types=1);

namespace App\Domain\Segment\SegmentEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentIds;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class DbalSegmentEffortRepository extends DbalRepository implements SegmentEffortRepository
{
    private const string RANKED_EFFORTS_FOR_SEGMENT = 'SELECT * FROM (
                    SELECT SegmentEffort.*, ROW_NUMBER() OVER (
                        PARTITION BY segmentId
                        ORDER BY elapsedTimeInSeconds ASC, segmentEffortId ASC
                    ) rank
                    FROM SegmentEffort
                    WHERE segmentId = :segmentId
                ) ranked';

    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
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

        $this->eventBus->publishEvents($segmentEffort->getRecordedEvents());
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        // The segments have to be collected before the delete, afterwards there is no way
        // left to tell which segment renders went stale.
        $segmentIds = SegmentIds::fromArray(array_map(
            SegmentId::fromString(...),
            $this->connection->executeQuery(
                'SELECT DISTINCT segmentId FROM SegmentEffort WHERE activityId = :activityId',
                ['activityId' => $activityId],
            )->fetchFirstColumn()
        ));

        $sql = 'DELETE FROM SegmentEffort
        WHERE activityId = :activityId';

        $this->connection->executeStatement($sql,
            [
                'activityId' => $activityId,
            ]
        );

        if ($segmentIds->isEmpty()) {
            return;
        }

        $this->eventBus->publishEvents([new SegmentEffortsWereDeleted($segmentIds)]);
    }

    public function find(SegmentEffortId $segmentEffortId): SegmentEffort
    {
        $sql = 'SELECT * FROM (
                    SELECT SegmentEffort.*, ROW_NUMBER() OVER (
                        PARTITION BY segmentId
                        ORDER BY elapsedTimeInSeconds ASC, segmentEffortId ASC
                    ) rank
                    FROM SegmentEffort
                    WHERE segmentId = (SELECT segmentId FROM SegmentEffort WHERE segmentEffortId = :segmentEffortId)
                ) ranked
                WHERE segmentEffortId = :segmentEffortId';

        if (!$result = $this->connection->executeQuery($sql, ['segmentEffortId' => $segmentEffortId])->fetchAssociative()) {
            throw new EntityNotFound(sprintf('segmentEffort "%s" not found', $segmentEffortId));
        }

        return $this->hydrate($result, (int) $result['rank']);
    }

    public function findTopXBySegmentId(SegmentId $segmentId, int $limit): SegmentEfforts
    {
        $sql = self::RANKED_EFFORTS_FOR_SEGMENT.' ORDER BY rank ASC LIMIT :limit';

        return SegmentEfforts::fromArray(array_map(
            fn (array $result): SegmentEffort => $this->hydrate($result, (int) $result['rank']),
            $this->connection->executeQuery(
                $sql,
                [
                    'segmentId' => $segmentId,
                    'limit' => $limit,
                ],
                ['limit' => ParameterType::INTEGER],
            )->fetchAllAssociative()
        ));
    }

    public function findBySegmentId(SegmentId $segmentId): SegmentEfforts
    {
        $sql = self::RANKED_EFFORTS_FOR_SEGMENT.' ORDER BY startDateTime DESC';

        return SegmentEfforts::fromArray(array_map(
            fn (array $result): SegmentEffort => $this->hydrate($result, (int) $result['rank']),
            $this->connection->executeQuery($sql, ['segmentId' => $segmentId])->fetchAllAssociative()
        ));
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

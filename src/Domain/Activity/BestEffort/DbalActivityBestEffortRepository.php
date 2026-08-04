<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Repository\DbalRepository;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivityBestEffortRepository extends DbalRepository implements ActivityBestEffortRepository
{
    public function add(ActivityBestEffort $activityBestEffort): void
    {
        $sql = 'INSERT INTO ActivityBestEffort (activityId, sportType, distanceInMeter, timeInSeconds)
        VALUES (:activityId, :sportType, :distanceInMeter, :timeInSeconds)';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityBestEffort->getActivityId(),
            'sportType' => $activityBestEffort->getSportType()->value,
            'distanceInMeter' => $activityBestEffort->getDistanceInMeter()->toInt(),
            'timeInSeconds' => $activityBestEffort->getTimeInSeconds(),
        ]);
    }

    public function hasData(): bool
    {
        $sql = 'SELECT 1 FROM ActivityBestEffort LIMIT 1';

        return (bool) $this->connection->executeQuery($sql)->fetchOne();
    }

    public function findActivityIdsThatNeedBestEffortsCalculation(): ActivityIds
    {
        $sql = 'SELECT Activity.activityId FROM Activity 
                  WHERE sportType IN (:sportTypes)
                  AND NOT EXISTS (
                    SELECT 1 FROM ActivityBestEffort WHERE ActivityBestEffort.activityId = Activity.activityId
                  )
                  AND EXISTS (
                    SELECT 1 FROM ActivityStream x
                    WHERE x.activityId = Activity.activityId AND x.streamType = :timeStreamType AND x.dataSize > 0
                  )
                  AND EXISTS (
                    SELECT 1 FROM ActivityStream y
                    WHERE y.activityId = Activity.activityId AND y.streamType = :distanceStreamType AND y.dataSize > 0
                  )';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery(
                $sql,
                [
                    'timeStreamType' => StreamType::TIME->value,
                    'distanceStreamType' => StreamType::DISTANCE->value,
                    'sportTypes' => array_map(
                        fn (SportType $sportType) => $sportType->value,
                        array_filter(SportType::cases(), fn (SportType $sportType): bool => $sportType->supportsBestEffortsStats())
                    ),
                ],
                [
                    'sportTypes' => ArrayParameterType::STRING,
                ]
            )->fetchFirstColumn()
        ));
    }

    public function findByActivity(ActivityId $activity): ActivityBestEfforts
    {
        $sql = 'SELECT activityId, sportType, distanceInMeter, timeInSeconds
                FROM (
                    SELECT
                        activityId,
                        sportType,
                        distanceInMeter,
                        timeInSeconds,
                        ROW_NUMBER() OVER (
                            PARTITION BY sportType, distanceInMeter
                            ORDER BY timeInSeconds ASC
                        ) AS rn
                    FROM ActivityBestEffort
                    WHERE sportType IN (:sportTypes)
                ) ranked
                WHERE rn = 1
                AND activityId = :activityId
                ORDER BY distanceInMeter ASC';

        return ActivityBestEfforts::fromArray(array_map(
            fn (array $result): ActivityBestEffort => ActivityBestEffort::fromState(
                activityId: ActivityId::fromString($result['activityId']),
                distanceInMeter: Meter::from($result['distanceInMeter']),
                sportType: SportType::from($result['sportType']),
                timeInSeconds: $result['timeInSeconds'],
            ),
            $this->connection->executeQuery(
                $sql,
                [
                    'sportTypes' => array_map(fn (SportType $sportType) => $sportType->value, SportTypes::thatSupportsBestEfforts()->toArray()),
                    'activityId' => (string) $activity,
                ],
                [
                    'sportTypes' => ArrayParameterType::STRING,
                ]
            )->fetchAllAssociative()
        ));
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityBestEffort WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }
}

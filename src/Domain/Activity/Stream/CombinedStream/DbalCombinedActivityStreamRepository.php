<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\CombinedStream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalCombinedActivityStreamRepository extends DbalRepository implements CombinedActivityStreamRepository
{
    public function add(CombinedActivityStream $combinedActivityStream): void
    {
        $sql = 'INSERT INTO CombinedActivityStream (activityId, unitSystem, streamTypes, data, maxYAxisValue)
        VALUES (:activityId, :unitSystem, :streamTypes, :data, :maxYAxisValue)';

        $this->connection->executeStatement($sql, [
            'activityId' => $combinedActivityStream->getActivityId(),
            'unitSystem' => $combinedActivityStream->getUnitSystem()->value,
            'streamTypes' => implode(',', $combinedActivityStream->getStreamTypes()->map(fn (CombinedStreamType $streamType) => $streamType->value)),
            'data' => Json::encodeAndCompress($combinedActivityStream->getData()),
            'maxYAxisValue' => $combinedActivityStream->getMaxYAxisValue(),
        ]);
    }

    public function findOneForActivityAndUnitSystem(ActivityId $activityId, UnitSystem $unitSystem): CombinedActivityStream
    {
        $sql = 'SELECT * FROM CombinedActivityStream 
                WHERE activityId = :activityId AND unitSystem = :unitSystem';
        if (!$result = $this->connection->executeQuery($sql,
            [
                'activityId' => $activityId,
                'unitSystem' => $unitSystem->value,
            ],
        )->fetchAssociative()) {
            throw new EntityNotFound('CombinedActivityStream not found');
        }

        return CombinedActivityStream::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            unitSystem: UnitSystem::from($result['unitSystem']),
            streamTypes: CombinedStreamTypes::fromArray(array_map(
                CombinedStreamType::from(...),
                explode(',', (string) $result['streamTypes'])
            )),
            data: Json::uncompressAndDecode($result['data']),
            maxYAxisValue: $result['maxYAxisValue'],
        );
    }

    public function findActivityIdsThatNeedStreamCombining(UnitSystem $unitSystem): ActivityIds
    {
        $sql = 'SELECT Activity.activityId FROM Activity 
                  WHERE sportType IN (:sportTypes)
                  AND NOT EXISTS (
                    SELECT 1 FROM CombinedActivityStream WHERE CombinedActivityStream.activityId = Activity.activityId 
                    AND CombinedActivityStream.unitSystem = :unitSystem
                  )
                  AND EXISTS (
                    SELECT 1 FROM ActivityStream y
                    WHERE y.activityId = Activity.activityId AND y.streamType = :timeStreamType AND y.dataSize > 0
                  )
                  AND EXISTS (
                    SELECT 1 FROM ActivityStream x
                    WHERE x.activityId = Activity.activityId AND x.streamType IN(:otherStreamTypes) AND x.dataSize > 0
                  )';

        $activityIds = [];
        foreach (ActivityType::cases() as $activityType) {
            $activityIds = array_merge($activityIds, $this->connection->executeQuery($sql,
                [
                    'unitSystem' => $unitSystem->value,
                    'timeStreamType' => CombinedStreamType::TIME->value,
                    'otherStreamTypes' => array_map(
                        fn (CombinedStreamType $streamType) => $streamType->getStreamType()->value,
                        CombinedStreamTypes::othersFor($activityType)->toArray()
                    ),
                    'sportTypes' => array_map(fn (SportType $sportType) => $sportType->value, $activityType->getSportTypes()->toArray()),
                ],
                [
                    'sportTypes' => ArrayParameterType::STRING,
                    'otherStreamTypes' => ArrayParameterType::STRING,
                ]
            )->fetchFirstColumn());
        }

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            array_unique($activityIds),
        ));
    }
}

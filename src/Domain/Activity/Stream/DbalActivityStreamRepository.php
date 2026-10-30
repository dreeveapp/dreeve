<?php

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class DbalActivityStreamRepository extends DbalRepository implements ActivityStreamRepository
{
    public function add(ActivityStream $stream): void
    {
        $sql = 'INSERT INTO ActivityStream (activityId, streamType, data, dataSize, createdOn)
                VALUES (:activityId, :streamType, :data, :dataSize, :createdOn)';

        $this->connection->executeStatement($sql, [
            'activityId' => $stream->getActivityId(),
            'streamType' => $stream->getStreamType()->value,
            'data' => Json::encodeAndCompress($stream->getData()),
            'dataSize' => count($stream->getData()),
            'createdOn' => $stream->getCreatedOn(),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityStream WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function hasOneForActivityAndStreamType(ActivityId $activityId, StreamType $streamType): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(*)')
            ->from('ActivityStream')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->andWhere('streamType = :streamType')
            ->setParameter('streamType', $streamType->value);

        return $queryBuilder->executeQuery()->fetchOne() > 0;
    }

    public function findActivityIdsByStreamType(StreamType $streamType): ActivityIds
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('ActivityStream')
            ->andWhere('streamType = :streamType')
            ->setParameter('streamType', $streamType->value);

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $queryBuilder->executeQuery()->fetchFirstColumn()
        ));
    }

    public function findOneByActivityAndStreamType(ActivityId $activityId, StreamType $streamType): ActivityStream
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('ActivityStream')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->andWhere('streamType = :streamType')
            ->setParameter('streamType', $streamType->value);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('ActivityStream %s-%s not found', $activityId, $streamType->value));
        }

        return $this->hydrate($result);
    }

    public function findByActivityId(ActivityId $activityId): ActivityStreams
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('ActivityStream')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        return ActivityStreams::fromArray(array_map(
            $this->hydrate(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityStream
    {
        return ActivityStream::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            streamType: StreamType::from($result['streamType']),
            streamData: Json::uncompressAndDecode($result['data']),
            createdOn: SerializableDateTime::fromString($result['createdOn']),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream\Metric;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivityStreamMetricRepository extends DbalRepository implements ActivityStreamMetricRepository
{
    public function add(ActivityStreamMetric $metric): void
    {
        $sql = 'INSERT INTO ActivityStreamMetric (activityId, streamType, metricType, data)
                VALUES (:activityId, :streamType, :metricType, :data)';

        $this->connection->executeStatement($sql, [
            'activityId' => $metric->getActivityId(),
            'streamType' => $metric->getStreamType()->value,
            'metricType' => $metric->getMetricType()->value,
            'data' => Json::encodeAndCompress($metric->getData()),
        ]);
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM ActivityStreamMetric WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function findActivityIdsWithoutBestAverages(): ActivityIds
    {
        $sql = 'SELECT DISTINCT s.activityId FROM ActivityStream s
                WHERE NOT EXISTS (
                    SELECT 1 FROM ActivityStreamMetric m
                    WHERE m.activityId = s.activityId
                    AND m.streamType = s.streamType
                    AND m.metricType = :metricType
                )
                AND s.streamType = :streamType
                ORDER BY s.activityId';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, [
                'metricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
                'streamType' => StreamType::WATTS->value,
            ], [
                'streamTypes' => ArrayParameterType::STRING,
            ])->fetchFirstColumn()
        ));
    }

    public function findActivityIdsWithoutNormalizedPower(): ActivityIds
    {
        $sql = 'SELECT DISTINCT s.activityId FROM ActivityStream s
                WHERE NOT EXISTS (
                    SELECT 1 FROM ActivityStreamMetric m
                    WHERE m.activityId = s.activityId
                    AND m.streamType = s.streamType
                    AND m.metricType = :metricType
                )
                AND s.streamType = :streamType
                ORDER BY s.activityId';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, [
                'metricType' => ActivityStreamMetricType::NORMALIZED_POWER->value,
                'streamType' => StreamType::WATTS->value,
            ])->fetchFirstColumn()
        ));
    }

    public function findActivityIdsWithoutDistributionValues(): ActivityIds
    {
        $sql = 'SELECT DISTINCT s.activityId FROM ActivityStream s
                WHERE NOT EXISTS (
                    SELECT 1 FROM ActivityStreamMetric m
                    WHERE m.activityId = s.activityId
                    AND m.streamType = s.streamType
                    AND m.metricType = :metricType
                )
                AND s.streamType IN(:streamTypes)
                ORDER BY s.activityId';

        return ActivityIds::fromArray(array_map(
            ActivityId::fromString(...),
            $this->connection->executeQuery($sql, [
                'metricType' => ActivityStreamMetricType::VALUE_DISTRIBUTION->value,
                'streamTypes' => array_map(
                    fn (StreamType $streamType) => $streamType->value,
                    StreamType::thatSupportDistributionValues()
                ),
            ], [
                'streamTypes' => ArrayParameterType::STRING,
            ])->fetchFirstColumn()
        ));
    }

    public function findByActivityIdAndMetricType(ActivityId $activityId, ActivityStreamMetricType $metricType): ActivityStreamMetrics
    {
        $sql = 'SELECT * FROM ActivityStreamMetric
                WHERE activityId = :activityId AND metricType = :metricType';

        return ActivityStreamMetrics::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery($sql, [
                'activityId' => $activityId,
                'metricType' => $metricType->value,
            ])->fetchAllAssociative()
        ));
    }

    public function findByActivityId(ActivityId $activityId): ActivityStreamMetrics
    {
        $sql = 'SELECT * FROM ActivityStreamMetric WHERE activityId = :activityId';

        return ActivityStreamMetrics::fromArray(array_map(
            $this->hydrate(...),
            $this->connection->executeQuery($sql, [
                'activityId' => $activityId,
            ])->fetchAllAssociative()
        ));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): ActivityStreamMetric
    {
        return ActivityStreamMetric::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            streamType: StreamType::from($result['streamType']),
            metricType: ActivityStreamMetricType::from($result['metricType']),
            data: Json::uncompressAndDecode($result['data']),
        );
    }
}

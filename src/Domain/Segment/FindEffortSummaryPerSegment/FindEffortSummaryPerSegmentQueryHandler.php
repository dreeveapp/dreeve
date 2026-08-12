<?php

declare(strict_types=1);

namespace App\Domain\Segment\FindEffortSummaryPerSegment;

use App\Domain\Activity\ActivityId;
use App\Domain\Segment\SegmentEffort\SegmentEffort;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentId;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class FindEffortSummaryPerSegmentQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindEffortSummaryPerSegment);

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT * FROM (
                    SELECT SegmentEffort.*,
                        ROW_NUMBER() OVER (
                            PARTITION BY segmentId
                            ORDER BY elapsedTimeInSeconds ASC, startDateTime DESC
                        ) rank,
                        COUNT(*) OVER (PARTITION BY segmentId) numberOfTimesRidden,
                        MAX(startDateTime) OVER (PARTITION BY segmentId) lastEffortDate
                    FROM SegmentEffort
                ) ranked
                WHERE rank = 1
            SQL
        )->fetchAllAssociative();

        $summaryPerSegmentId = [];
        foreach ($results as $result) {
            $summaryPerSegmentId[(string) SegmentId::fromString($result['segmentId'])] = SegmentEffortSummary::create(
                numberOfTimesRidden: (int) $result['numberOfTimesRidden'],
                bestEffort: SegmentEffort::fromState(
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
                    rank: (int) $result['rank'],
                ),
                lastEffortDate: SerializableDateTime::fromString($result['lastEffortDate']),
            );
        }

        return new FindEffortSummaryPerSegmentResponse($summaryPerSegmentId);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort\FindBestEfforts;

use App\Domain\Activity\ActivityExclusion;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\BestEffort\ActivityBestEfforts;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\CQRS\Query\Query;
use App\Infrastructure\CQRS\Query\QueryHandler;
use App\Infrastructure\CQRS\Query\Response;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class FindBestEffortsQueryHandler implements QueryHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindBestEfforts);
        $activityType = $query->getActivityType();
        $sportTypes = SportTypes::thatSupportsBestEfforts()->filter(
            fn (SportType $sportType): bool => !$activityType instanceof ActivityType || $sportType->getActivityType() === $activityType
        );
        $distanceInMeter = $query->getDistance()?->toMeter()->toInt();

        $results = $this->connection->executeQuery(
            <<<SQL
                SELECT ActivityBestEffort.activityId,
                       ActivityBestEffort.sportType,
                       ActivityBestEffort.distanceInMeter,
                       ActivityBestEffort.timeInSeconds,
                       Activity.startDateTime
                FROM ActivityBestEffort
                INNER JOIN Activity ON ActivityBestEffort.activityId = Activity.activityId
                WHERE ActivityBestEffort.sportType IN (:sportTypes)
                AND (:distanceInMeter IS NULL OR ActivityBestEffort.distanceInMeter = :distanceInMeter)
                AND NOT EXISTS (SELECT 1 FROM json_each(Activity.excludedFrom) WHERE json_each.value = :excludedFrom)
                ORDER BY ActivityBestEffort.timeInSeconds ASC,
                         Activity.startDateTime ASC,
                         ActivityBestEffort.activityId ASC
            SQL,
            [
                'sportTypes' => $sportTypes->map(fn (SportType $sportType): string => $sportType->value),
                'distanceInMeter' => $distanceInMeter,
                'excludedFrom' => ActivityExclusion::BEST_EFFORTS->value,
            ],
            [
                'sportTypes' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        $bestEfforts = ActivityBestEfforts::empty();
        $startDateTimePerActivity = [];

        foreach ($results as $result) {
            $activityId = ActivityId::fromString((string) $result['activityId']);

            $bestEfforts->add(ActivityBestEffort::fromState(
                activityId: $activityId,
                distanceInMeter: Meter::from((float) $result['distanceInMeter']),
                sportType: SportType::from((string) $result['sportType']),
                timeInSeconds: (int) $result['timeInSeconds'],
            ));

            $startDateTimePerActivity[(string) $activityId] = SerializableDateTime::fromString((string) $result['startDateTime']);
        }

        return new FindBestEffortsResponse(
            bestEfforts: $bestEfforts,
            startDateTimePerActivity: $startDateTimePerActivity,
        );
    }
}

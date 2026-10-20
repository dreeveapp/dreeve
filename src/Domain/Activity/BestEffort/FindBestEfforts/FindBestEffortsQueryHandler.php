<?php

declare(strict_types=1);

namespace App\Domain\Activity\BestEffort\FindBestEfforts;

use App\Domain\Activity\ActivityExclusion;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\BestEffort\ActivityBestEffort;
use App\Domain\Activity\BestEffort\ActivityBestEfforts;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Settings\SettingsRepository;
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
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function handle(Query $query): Response
    {
        assert($query instanceof FindBestEfforts);
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
                AND ActivityBestEffort.activityId NOT IN (:excludedActivityIds)
                ORDER BY ActivityBestEffort.timeInSeconds ASC,
                         Activity.startDateTime ASC,
                         ActivityBestEffort.activityId ASC
            SQL,
            [
                'sportTypes' => SportTypes::thatSupportsBestEfforts()->map(fn (SportType $sportType): string => $sportType->value),
                'excludedActivityIds' => $this->settingsRepository->activityExclusions()->getExcludedActivityIds(ActivityExclusion::BEST_EFFORTS)->map(fn (ActivityId $activityId): string => (string) $activityId) ?: ['unexisting-id'],
            ],
            [
                'sportTypes' => ArrayParameterType::STRING,
                'excludedActivityIds' => ArrayParameterType::STRING,
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

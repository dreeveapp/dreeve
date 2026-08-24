<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DbalActivityRepository extends DbalRepository implements ActivityRepository
{
    public function __construct(
        Connection $connection,
        private EventBus $eventBus,
    ) {
        parent::__construct($connection);
    }

    public function find(ActivityId $activityId): Activity
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select(ActivityHydrator::columns())
            ->from('Activity')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        return ActivityHydrator::hydrate($result);
    }

    public function findAll(): Activities
    {
        $results = $this->connection->executeQuery(
            'SELECT '.ActivityHydrator::columns().' FROM Activity ORDER BY startDateTime DESC'
        )->fetchAllAssociative();

        return Activities::fromArray(array_map(ActivityHydrator::hydrate(...), $results));
    }

    public function findMostRecent(int $limit, ?SportTypes $restrictToSportTypes = null): Activities
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select(ActivityHydrator::columns())
            ->from('Activity')
            ->orderBy('startDateTime', 'DESC')
            ->setMaxResults($limit);

        if ($restrictToSportTypes instanceof SportTypes && !$restrictToSportTypes->isEmpty()) {
            $queryBuilder->andWhere('sportType IN (:sportTypes)')
                ->setParameter(
                    key: 'sportTypes',
                    value: array_map(fn (SportType $sportType): string => $sportType->value, $restrictToSportTypes->toArray()),
                    type: ArrayParameterType::STRING
                );
        }

        return Activities::fromArray(array_map(
            ActivityHydrator::hydrate(...),
            $queryBuilder->executeQuery()->fetchAllAssociative()
        ));
    }

    public function findByIds(ActivityIds $activityIds): Activities
    {
        if ($activityIds->isEmpty()) {
            return Activities::empty();
        }

        $results = $this->connection->executeQuery(
            'SELECT '.ActivityHydrator::columns().' FROM Activity WHERE activityId IN (:activityIds) ORDER BY startDateTime DESC',
            [
                'activityIds' => array_map(strval(...), $activityIds->toArray()),
            ],
            [
                'activityIds' => ArrayParameterType::STRING,
            ]
        )->fetchAllAssociative();

        return Activities::fromArray(array_map(ActivityHydrator::hydrate(...), $results));
    }

    public function findByDateRange(SerializableDateTime $from, SerializableDateTime $till): Activities
    {
        $results = $this->connection->executeQuery(
            'SELECT '.ActivityHydrator::columns().' FROM Activity WHERE startDateTime >= :from AND startDateTime < :till ORDER BY startDateTime DESC',
            [
                'from' => $from->format('Y-m-d H:i:s'),
                'till' => $till->format('Y-m-d H:i:s'),
            ]
        )->fetchAllAssociative();

        return Activities::fromArray(array_map(ActivityHydrator::hydrate(...), $results));
    }

    public function findWithRawData(ActivityId $activityId): ActivityWithRawData
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select(ActivityHydrator::columns().', data')
            ->from('Activity')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        return ActivityWithRawData::fromState(
            activity: ActivityHydrator::hydrate($result),
            rawData: Json::decode($result['data']),
        );
    }

    public function exists(ActivityId $activityId): bool
    {
        return !empty($this->connection->executeQuery('SELECT 1 FROM Activity WHERE activityId = :activityId', [
            'activityId' => $activityId,
        ])->fetchOne());
    }

    public function add(ActivityWithRawData $activityWithRawData): void
    {
        $sql = 'INSERT INTO Activity (
            activityId, startDateTime, sportType, activityType, worldType, importSource, externalReferenceId, name, description, distance,
            elevation, startingCoordinateLatitude, startingCoordinateLongitude, calories, kilojoules,
            averagePower, maxPower, averageSpeed, maxSpeed, averageHeartRate, maxHeartRate,
            averageCadence,movingTimeInSeconds, elapsedTimeInSeconds, deviceName, connectedSensors, totalImageCount, localImagePaths,
            polyline, routeGeography, weather, gearId, data, isCommute, isGroupActivity, streamsAreImported, workoutType
        ) VALUES(
            :activityId, :startDateTime, :sportType, :activityType, :worldType, :importSource, :externalReferenceId, :name, :description, :distance,
            :elevation, :startingCoordinateLatitude, :startingCoordinateLongitude, :calories, :kilojoules,
            :averagePower, :maxPower, :averageSpeed, :maxSpeed, :averageHeartRate, :maxHeartRate,
            :averageCadence, :movingTimeInSeconds, :elapsedTimeInSeconds, :deviceName, :connectedSensors, :totalImageCount, :localImagePaths,
            :polyline, :routeGeography, :weather, :gearId, :data, :isCommute, :isGroupActivity, :streamsAreImported, :workoutType
        )';

        $activity = $activityWithRawData->getActivity();
        $this->connection->executeStatement($sql, [
            'activityId' => $activity->getId(),
            'startDateTime' => $activity->getStartDate(),
            'sportType' => $activity->getSportType()->value,
            'worldType' => $activity->getWorldType()->value,
            'importSource' => $activity->getImportSource()->value,
            'externalReferenceId' => $activity->getExternalReferenceId(),
            'activityType' => $activity->getSportType()->getActivityType()->value,
            'name' => $activity->getOriginalName(),
            'description' => $activity->getDescription(),
            'distance' => $activity->getDistance()->toMeter()->toInt(),
            'elevation' => $activity->getElevation()->toInt(),
            'startingCoordinateLatitude' => $activity->getStartingCoordinate()?->getLatitude()->toFloat(),
            'startingCoordinateLongitude' => $activity->getStartingCoordinate()?->getLongitude()->toFloat(),
            'calories' => $activity->getCalories(),
            'kilojoules' => $activity->getKilojoules(),
            'averagePower' => $activity->getAveragePower(),
            'maxPower' => $activity->getMaxPower(),
            'averageSpeed' => $activity->getAverageSpeed()->toFloat(),
            'maxSpeed' => $activity->getMaxSpeed()->toFloat(),
            'averageHeartRate' => $activity->getAverageHeartRate(),
            'maxHeartRate' => $activity->getMaxHeartRate(),
            'averageCadence' => $activity->getAverageCadence(),
            'movingTimeInSeconds' => $activity->getMovingTimeInSeconds(),
            'elapsedTimeInSeconds' => $activity->getElapsedTimeInSeconds(),
            'deviceName' => $activity->getDeviceName(),
            'connectedSensors' => $activity->getConnectedSensors() instanceof ConnectedSensors ? Json::encode($activity->getConnectedSensors()) : null,
            'totalImageCount' => $activity->getTotalImageCount(),
            'localImagePaths' => implode(',', $activity->getLocalImagePaths()),
            'polyline' => $activity->getEncodedPolyline(),
            'routeGeography' => Json::encode($activity->getRouteGeography()),
            'weather' => $activity->getWeather() instanceof Weather ? Json::encode($activity->getWeather()) : null,
            'gearId' => $activity->getGearId(),
            'data' => Json::encode($this->cleanData($activityWithRawData->getRawData())),
            'isCommute' => (int) $activity->isCommute(),
            'isGroupActivity' => (int) $activity->isGroupActivity(),
            'streamsAreImported' => 0,
            'workoutType' => $activity->getWorkoutType()?->value,
        ]);

        $this->eventBus->publishEvents($activity->getRecordedEvents());
    }

    public function update(ActivityWithRawData $activityWithRawData): void
    {
        $sql = 'UPDATE Activity SET
                    startDateTime = :startDateTime,
                    name = :name,
                    description = :description,
                    deviceName = :deviceName,
                    sportType = :sportType,
                    activityType = :activityType,
                    worldType = :worldType,
                    distance = :distance,
                    averageSpeed = :averageSpeed,
                    maxSpeed = :maxSpeed,
                    movingTimeInSeconds = :movingTimeInSeconds,
                    elapsedTimeInSeconds = :elapsedTimeInSeconds,
                    elevation = :elevation,
                    calories = :calories,
                    polyline = :polyline,
                    startingCoordinateLatitude = :startingCoordinateLatitude,
                    startingCoordinateLongitude = :startingCoordinateLongitude,
                    routeGeography = :routeGeography,
                    gearId = :gearId, 
                    totalImageCount = :totalImageCount,
                    localImagePaths = :localImagePaths,
                    data = :data,
                    isCommute = :isCommute,
                    isGroupActivity = :isGroupActivity,
                    workoutType = :workoutType    
                    WHERE activityId = :activityId';

        $activity = $activityWithRawData->getActivity();
        $this->connection->executeStatement($sql, [
            'activityId' => $activity->getId(),
            'startDateTime' => $activity->getStartDate(),
            'sportType' => $activity->getSportType()->value,
            'activityType' => $activity->getSportType()->getActivityType()->value,
            'worldType' => $activity->getWorldType()->value,
            'name' => $activity->getOriginalName(),
            'description' => $activity->getDescription(),
            'deviceName' => $activity->getDeviceName(),
            'distance' => $activity->getDistance()->toMeter()->toInt(),
            'elevation' => $activity->getElevation()->toInt(),
            'calories' => $activity->getCalories(),
            'averageSpeed' => $activity->getAverageSpeed()->toFloat(),
            'maxSpeed' => $activity->getMaxSpeed()->toFloat(),
            'movingTimeInSeconds' => $activity->getMovingTimeInSeconds(),
            'elapsedTimeInSeconds' => $activity->getElapsedTimeInSeconds(),
            'polyline' => $activity->getEncodedPolyline(),
            'startingCoordinateLatitude' => $activity->getStartingCoordinate()?->getLatitude()->toFloat(),
            'startingCoordinateLongitude' => $activity->getStartingCoordinate()?->getLongitude()->toFloat(),
            'routeGeography' => Json::encode($activity->getRouteGeography()),
            'gearId' => $activity->getGearId(),
            'totalImageCount' => $activity->getTotalImageCount(),
            'localImagePaths' => implode(',', $activity->getLocalImagePaths()),
            'isCommute' => (int) $activity->isCommute(),
            'isGroupActivity' => (int) $activity->isGroupActivity(),
            'workoutType' => $activity->getWorkoutType()?->value,
            'data' => Json::encode($this->cleanData($activityWithRawData->getRawData())),
        ]);

        $this->eventBus->publishEvents($activity->getRecordedEvents());
    }

    public function delete(ActivityId $activityId): void
    {
        // The start date determines which year scoped cache tags need to be invalidated,
        // so it has to be read before the row is gone.
        $startDateTime = $this->connection->executeQuery(
            'SELECT startDateTime FROM Activity WHERE activityId = :activityId',
            ['activityId' => (string) $activityId],
        )->fetchOne();

        $sql = 'DELETE FROM Activity WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);

        if (false === $startDateTime) {
            return;
        }

        $this->eventBus->publishEvents([
            new ActivityWasDeleted(
                activityId: $activityId,
                startDate: SerializableDateTime::fromString((string) $startDateTime)
            ),
        ]);
    }

    public function activityNeedsStreamImport(ActivityId $activityId): bool
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('activityId')
            ->from('Activity')
            ->andWhere('streamsAreImported = 0 OR streamsAreImported IS NULL')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        return !empty($queryBuilder->fetchOne());
    }

    public function markActivityStreamsAsImported(ActivityId $activityId): void
    {
        $sql = 'UPDATE Activity SET streamsAreImported = 1 WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
        ]);
    }

    public function markActivitiesForDeletion(ActivityIds $activityIds): void
    {
        $sql = 'UPDATE Activity SET markedForDeletion = 1 WHERE activityId IN (:activityIds)';

        $this->connection->executeStatement($sql, [
            'activityIds' => array_map(strval(...), $activityIds->toArray()),
        ],
            [
                'activityIds' => ArrayParameterType::STRING,
            ]);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function cleanData(array $data): array
    {
        if (isset($data['map']['polyline'])) {
            unset($data['map']['polyline']);
        }

        return $data;
    }
}

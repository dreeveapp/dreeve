<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\ArrayParameterType;

final readonly class DbalActivityRepository extends DbalRepository implements ActivityRepository
{
    public function find(ActivityId $activityId): Activity
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('Activity')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        return $this->hydrate($result);
    }

    public function findAll(): Activities
    {
        $results = $this->connection->executeQuery(
            'SELECT * FROM Activity ORDER BY startDateTime DESC'
        )->fetchAllAssociative();

        return Activities::fromArray(array_map($this->hydrate(...), $results));
    }

    public function findWithRawData(ActivityId $activityId): ActivityWithRawData
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('Activity')
            ->andWhere('activityId = :activityId')
            ->setParameter('activityId', $activityId);

        if (!$result = $queryBuilder->executeQuery()->fetchAssociative()) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        return ActivityWithRawData::fromState(
            activity: $this->hydrate($result),
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
            averageCadence,movingTimeInSeconds, elapsedTimeInSeconds, deviceName, totalImageCount, localImagePaths,
            polyline, routeGeography, weather, gearId, data, isCommute, streamsAreImported, workoutType
        ) VALUES(
            :activityId, :startDateTime, :sportType, :activityType, :worldType, :importSource, :externalReferenceId, :name, :description, :distance,
            :elevation, :startingCoordinateLatitude, :startingCoordinateLongitude, :calories, :kilojoules,
            :averagePower, :maxPower, :averageSpeed, :maxSpeed, :averageHeartRate, :maxHeartRate,
            :averageCadence, :movingTimeInSeconds, :elapsedTimeInSeconds, :deviceName, :totalImageCount, :localImagePaths,
            :polyline, :routeGeography, :weather, :gearId, :data, :isCommute, :streamsAreImported, :workoutType
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
            'totalImageCount' => $activity->getTotalImageCount(),
            'localImagePaths' => implode(',', $activity->getLocalImagePaths()),
            'polyline' => $activity->getEncodedPolyline(),
            'routeGeography' => Json::encode($activity->getRouteGeography()),
            'weather' => $activity->getWeather() instanceof Weather ? Json::encode($activity->getWeather()) : null,
            'gearId' => $activity->getGearId(),
            'data' => Json::encode($this->cleanData($activityWithRawData->getRawData())),
            'isCommute' => (int) $activity->isCommute(),
            'streamsAreImported' => 0,
            'workoutType' => $activity->getWorkoutType()?->value,
        ]);
    }

    public function update(ActivityWithRawData $activityWithRawData): void
    {
        $sql = 'UPDATE Activity SET
                    name = :name,
                    description = :description,
                    deviceName = :deviceName,
                    sportType = :sportType,
                    activityType = :activityType,
                    distance = :distance,
                    averageSpeed = :averageSpeed,
                    maxSpeed = :maxSpeed,
                    movingTimeInSeconds = :movingTimeInSeconds,
                    elevation = :elevation,
                    polyline = :polyline,
                    startingCoordinateLatitude = :startingCoordinateLatitude,
                    startingCoordinateLongitude = :startingCoordinateLongitude,
                    routeGeography = :routeGeography,
                    gearId = :gearId, 
                    totalImageCount = :totalImageCount,
                    localImagePaths = :localImagePaths,
                    data = :data,
                    isCommute = :isCommute,
                    workoutType = :workoutType    
                    WHERE activityId = :activityId';

        $activity = $activityWithRawData->getActivity();
        $this->connection->executeStatement($sql, [
            'activityId' => $activity->getId(),
            'sportType' => $activity->getSportType()->value,
            'activityType' => $activity->getSportType()->getActivityType()->value,
            'name' => $activity->getOriginalName(),
            'description' => $activity->getDescription(),
            'deviceName' => $activity->getDeviceName(),
            'distance' => $activity->getDistance()->toMeter()->toInt(),
            'elevation' => $activity->getElevation()->toInt(),
            'averageSpeed' => $activity->getAverageSpeed()->toFloat(),
            'maxSpeed' => $activity->getMaxSpeed()->toFloat(),
            'movingTimeInSeconds' => $activity->getMovingTimeInSeconds(),
            'polyline' => $activity->getEncodedPolyline(),
            'startingCoordinateLatitude' => $activity->getStartingCoordinate()?->getLatitude()->toFloat(),
            'startingCoordinateLongitude' => $activity->getStartingCoordinate()?->getLongitude()->toFloat(),
            'routeGeography' => Json::encode($activity->getRouteGeography()),
            'gearId' => $activity->getGearId(),
            'totalImageCount' => $activity->getTotalImageCount(),
            'localImagePaths' => implode(',', $activity->getLocalImagePaths()),
            'isCommute' => (int) $activity->isCommute(),
            'workoutType' => $activity->getWorkoutType()?->value,
            'data' => Json::encode($this->cleanData($activityWithRawData->getRawData())),
        ]);
    }

    public function delete(ActivityId $activityId): void
    {
        $sql = 'DELETE FROM Activity WHERE activityId = :activityId';

        $this->connection->executeStatement($sql, [
            'activityId' => $activityId,
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
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result): Activity
    {
        $startDateTime = SerializableDateTime::fromString($result['startDateTime']);
        $sportType = SportType::from($result['sportType']);

        return Activity::fromState(
            activityId: ActivityId::fromString($result['activityId']),
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: WorldType::from($result['worldType']),
            importSource: ImportSource::from($result['importSource']),
            externalReferenceId: ExternalReferenceId::fromOptionalString($result['externalReferenceId'] ?? null),
            name: '' !== trim((string) $result['name']) ? ActivityName::fromString($result['name']) : ActivityName::from($startDateTime, $sportType),
            description: $result['description'] ?: '',
            distance: Meter::from($result['distance'])->toKilometer(),
            elevation: Meter::from($result['elevation'] ?: 0),
            startingCoordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString((string) $result['startingCoordinateLatitude']),
                Longitude::fromOptionalString((string) $result['startingCoordinateLongitude'])
            ),
            calories: (int) ($result['calories'] ?? 0),
            kilojoules: ((int) $result['kilojoules']) ?: null,
            averagePower: ((int) $result['averagePower']) ?: null,
            maxPower: ((int) $result['maxPower']) ?: null,
            averageSpeed: KmPerHour::from($result['averageSpeed']),
            maxSpeed: KmPerHour::from($result['maxSpeed']),
            averageHeartRate: isset($result['averageHeartRate']) ? (int) round($result['averageHeartRate']) : null,
            maxHeartRate: isset($result['maxHeartRate']) ? (int) round($result['maxHeartRate']) : null,
            averageCadence: isset($result['averageCadence']) ? (int) round($result['averageCadence']) : null,
            movingTimeInSeconds: $result['movingTimeInSeconds'] ?: 0,
            elapsedTimeInSeconds: $result['elapsedTimeInSeconds'] ?: 0,
            deviceName: $result['deviceName'],
            totalImageCount: $result['totalImageCount'] ?: 0,
            localImagePaths: $result['localImagePaths'] ? explode(',', (string) $result['localImagePaths']) : [],
            polyline: $result['polyline'],
            routeGeography: RouteGeography::create(Json::decode($result['routeGeography'] ?? '[]')),
            weather: $result['weather'],
            gearId: GearId::fromOptionalString($result['gearId']),
            isCommute: (bool) $result['isCommute'],
            workoutType: WorkoutType::tryFrom($result['workoutType'] ?? ''),
        );
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

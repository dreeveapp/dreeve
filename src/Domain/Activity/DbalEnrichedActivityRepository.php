<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\PowerOutputs;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Connection;

final readonly class DbalEnrichedActivityRepository extends DbalRepository implements EnrichedActivityRepository
{
    private const string ACTIVITY_COLUMNS = 'a.activityId, a.startDateTime, a.sportType, a.worldType, a.importSource,
                       a.externalReferenceId, a.name, a.description, a.distance, a.elevation,
                       a.startingCoordinateLatitude, a.startingCoordinateLongitude, a.calories, a.kilojoules,
                       a.averagePower, a.maxPower, a.averageSpeed, a.maxSpeed, a.averageHeartRate, a.maxHeartRate,
                       a.averageCadence, a.movingTimeInSeconds, a.elapsedTimeInSeconds, a.deviceName,
                       a.totalImageCount, a.localImagePaths, a.polyline, a.routeGeography, a.weather, a.gearId,
                       a.isCommute, a.workoutType';

    private const string ENRICHMENT_JOINS = 'LEFT JOIN Gear g ON g.gearId = a.gearId
                LEFT JOIN ActivityStreamMetric np
                       ON np.activityId = a.activityId AND np.streamType = :wattsStreamType AND np.metricType = :normalizedPowerMetricType
                LEFT JOIN ActivityStreamMetric ba
                       ON ba.activityId = a.activityId AND ba.streamType = :wattsStreamType AND ba.metricType = :bestAveragesMetricType
                LEFT JOIN ActivityStreamMetric cad
                       ON cad.activityId = a.activityId AND cad.streamType = :cadenceStreamType AND cad.metricType = :valueDistributionMetricType';

    private const string ENRICHMENT_COLUMNS = 'g.name AS enrichedGearName,
                       np.data AS enrichedNormalizedPower,
                       ba.data AS enrichedBestAverages,
                       cad.data AS enrichedCadenceDistribution';

    public function __construct(
        Connection $connection,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct($connection);
    }

    public function find(ActivityId $activityId): EnrichedActivity
    {
        $result = $this->connection->executeQuery(
            sprintf(
                'SELECT %s, %s FROM Activity a %s WHERE a.activityId = :activityId',
                self::ACTIVITY_COLUMNS,
                self::ENRICHMENT_COLUMNS,
                self::ENRICHMENT_JOINS,
            ),
            [...$this->enrichmentParameters(), 'activityId' => $activityId],
        )->fetchAssociative();

        if (!$result) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        return $this->hydrate($result, $this->athleteWeightHistory());
    }

    public function findAll(): array
    {
        $results = $this->connection->executeQuery(
            sprintf(
                'SELECT %s, %s FROM Activity a %s ORDER BY a.startDateTime DESC',
                self::ACTIVITY_COLUMNS,
                self::ENRICHMENT_COLUMNS,
                self::ENRICHMENT_JOINS,
            ),
            $this->enrichmentParameters(),
        )->fetchAllAssociative();

        $athleteWeightHistory = $this->athleteWeightHistory();

        return array_map(
            fn (array $result): EnrichedActivity => $this->hydrate($result, $athleteWeightHistory),
            $results
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrate(array $result, AthleteWeightHistory $athleteWeightHistory): EnrichedActivity
    {
        $activity = ActivityHydrator::hydrate($result);

        return EnrichedActivity::fromState(
            activity: $activity,
            normalizedPower: $this->normalizedPower($result['enrichedNormalizedPower']),
            maxCadence: $this->maxCadence($result['enrichedCadenceDistribution']),
            gearName: $result['enrichedGearName'],
            bestPowerOutputs: $this->bestPowerOutputs($result['enrichedBestAverages'], $activity, $athleteWeightHistory),
        );
    }

    /**
     * @return array<string, string>
     */
    private function enrichmentParameters(): array
    {
        return [
            'wattsStreamType' => StreamType::WATTS->value,
            'cadenceStreamType' => StreamType::CADENCE->value,
            'normalizedPowerMetricType' => ActivityStreamMetricType::NORMALIZED_POWER->value,
            'bestAveragesMetricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
            'valueDistributionMetricType' => ActivityStreamMetricType::VALUE_DISTRIBUTION->value,
        ];
    }

    private function athleteWeightHistory(): AthleteWeightHistory
    {
        return $this->settingsRepository->general()->getAthleteWeightHistory(
            $this->settingsRepository->appearance()->getUnitSystem()
        );
    }

    private function normalizedPower(?string $data): ?int
    {
        if (is_null($data)) {
            return null;
        }

        return Json::uncompressAndDecode($data)[0] ?? null;
    }

    private function maxCadence(?string $data): ?int
    {
        if (is_null($data)) {
            return null;
        }

        if ([] === $cadenceDistribution = Json::uncompressAndDecode($data)) {
            return null;
        }

        return max(array_keys($cadenceDistribution));
    }

    private function bestPowerOutputs(?string $data, Activity $activity, AthleteWeightHistory $athleteWeightHistory): PowerOutputs
    {
        if (is_null($data)) {
            return PowerOutputs::empty();
        }

        try {
            $athleteWeight = $athleteWeightHistory->find($activity->getStartDate())->getWeightInKg();
        } catch (EntityNotFound) {
            return PowerOutputs::empty();
        }

        return PowerOutputs::fromBestAverages(
            bestAverages: Json::uncompressAndDecode($data),
            athleteWeight: $athleteWeight,
        );
    }
}

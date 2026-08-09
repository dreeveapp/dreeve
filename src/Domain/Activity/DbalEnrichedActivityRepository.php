<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\PowerOutputs;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Serialization\Json;
use Doctrine\DBAL\Connection;

final readonly class DbalEnrichedActivityRepository extends DbalRepository implements EnrichedActivityRepository
{
    public function __construct(
        Connection $connection,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct($connection);
    }

    public function find(ActivityId $activityId): EnrichedActivity
    {
        $sql = 'SELECT a.*,
                       g.name AS enrichedGearName,
                       np.data AS enrichedNormalizedPower,
                       ba.data AS enrichedBestAverages,
                       cad.data AS enrichedCadenceStream
                FROM Activity a
                LEFT JOIN Gear g ON g.gearId = a.gearId
                LEFT JOIN ActivityStreamMetric np
                       ON np.activityId = a.activityId AND np.streamType = :wattsStreamType AND np.metricType = :normalizedPowerMetricType
                LEFT JOIN ActivityStreamMetric ba
                       ON ba.activityId = a.activityId AND ba.streamType = :wattsStreamType AND ba.metricType = :bestAveragesMetricType
                LEFT JOIN ActivityStream cad
                       ON cad.activityId = a.activityId AND cad.streamType = :cadenceStreamType
                WHERE a.activityId = :activityId';

        $result = $this->connection->executeQuery($sql, [
            'activityId' => $activityId,
            'wattsStreamType' => StreamType::WATTS->value,
            'cadenceStreamType' => StreamType::CADENCE->value,
            'normalizedPowerMetricType' => ActivityStreamMetricType::NORMALIZED_POWER->value,
            'bestAveragesMetricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
        ])->fetchAssociative();

        if (!$result) {
            throw new EntityNotFound(sprintf('Activity "%s" not found', $activityId));
        }

        $activity = ActivityHydrator::hydrate($result);

        return EnrichedActivity::fromState(
            activity: $activity,
            normalizedPower: $this->normalizedPower($result['enrichedNormalizedPower']),
            maxCadence: $this->maxCadence($result['enrichedCadenceStream']),
            gearName: $result['enrichedGearName'],
            bestPowerOutputs: $this->bestPowerOutputs($result['enrichedBestAverages'], $activity),
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

        if ([] === $cadences = Json::uncompressAndDecode($data)) {
            return null;
        }

        return max($cadences);
    }

    private function bestPowerOutputs(?string $data, Activity $activity): PowerOutputs
    {
        if (is_null($data)) {
            return PowerOutputs::empty();
        }

        $athleteWeightHistory = $this->settingsRepository->general()->getAthleteWeightHistory(
            $this->settingsRepository->appearance()->getUnitSystem()
        );

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

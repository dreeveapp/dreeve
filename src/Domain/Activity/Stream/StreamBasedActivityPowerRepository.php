<?php

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Carbon\CarbonInterval;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class StreamBasedActivityPowerRepository implements ActivityPowerRepository
{
    /** @var array<string, PowerOutputs> */
    public static array $cachedPowerOutputs = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ActivityIdRepository $activityIdRepository,
        private readonly ActivitySummaryRepository $activitySummaryRepository,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    public function findBest(ActivityId $activityId): PowerOutputs
    {
        $this->buildStaticCaches();

        return StreamBasedActivityPowerRepository::$cachedPowerOutputs[(string) $activityId];
    }

    private function buildStaticCaches(): void
    {
        if ([] !== StreamBasedActivityPowerRepository::$cachedPowerOutputs) {
            return;
        }

        $athleteWeightHistory = $this->settingsRepository->general()->getAthleteWeightHistory($this->settingsRepository->appearance()->getUnitSystem());

        $activityIds = $this->activityIdRepository->findAll();
        foreach ($activityIds as $activityId) {
            StreamBasedActivityPowerRepository::$cachedPowerOutputs[(string) $activityId] = PowerOutputs::empty();
        }

        $results = $this->connection->executeQuery(
            'SELECT activityId, data FROM ActivityStreamMetric
             WHERE streamType = :streamType AND metricType = :metricType',
            [
                'streamType' => StreamType::WATTS->value,
                'metricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
            ]
        )->fetchAllAssociative();

        foreach ($results as $result) {
            $activityId = ActivityId::fromString($result['activityId']);
            $bestAverages = Json::uncompressAndDecode($result['data']);
            $activitySummary = $this->activitySummaryRepository->find($activityId);

            try {
                $athleteWeight = $athleteWeightHistory->find($activitySummary->getStartDate())->getWeightInKg();
            } catch (EntityNotFound) {
                continue; // @codeCoverageIgnore
            }

            foreach (self::TIME_INTERVALS_IN_SECONDS_REDACTED as $timeIntervalInSeconds) {
                $interval = CarbonInterval::seconds($timeIntervalInSeconds);
                if (!isset($bestAverages[$timeIntervalInSeconds])) {
                    continue;
                }
                $bestAverageForTimeInterval = $bestAverages[$timeIntervalInSeconds];

                $relativePower = $athleteWeight->toFloat() > 0 ? round($bestAverageForTimeInterval / $athleteWeight->toFloat(), 2) : 0;
                StreamBasedActivityPowerRepository::$cachedPowerOutputs[(string) $activityId]->add(PowerOutput::fromState(
                    timeIntervalInSeconds: $timeIntervalInSeconds,
                    formattedTimeInterval: 0 !== (int) $interval->totalHours ? $interval->totalHours.' h' : (0 !== (int) $interval->totalMinutes ? $interval->totalMinutes.' m' : $interval->totalSeconds.' s'),
                    power: $bestAverageForTimeInterval,
                    relativePower: $relativePower,
                ));
            }
        }
    }

    public function findBestForSportTypes(SportTypes $sportTypes): PowerOutputs
    {
        return $this->buildBestFor(
            sportTypes: $sportTypes,
            dateRange: null
        );
    }

    public function findBestForSportTypesInDateRange(SportTypes $sportTypes, DateRange $dateRange): PowerOutputs
    {
        return $this->buildBestFor(
            sportTypes: $sportTypes,
            dateRange: $dateRange
        );
    }

    private function buildBestFor(SportTypes $sportTypes, ?DateRange $dateRange): PowerOutputs
    {
        $powerOutputs = PowerOutputs::empty();

        if (!$dateRange instanceof DateRange) {
            $dateRange = DateRange::fromDates(
                from: SerializableDateTime::fromString('1970-01-01 00:00:00'),
                till: SerializableDateTime::fromString('2100-01-01 00:00:00')
            );
        }

        $sql = 'SELECT m.activityId, m.data FROM ActivityStreamMetric m
                INNER JOIN Activity a ON a.activityId = m.activityId
                WHERE m.streamType = :streamType
                AND m.metricType = :metricType
                AND a.sportType IN(:sportTypes)
                AND a.startDateTime >= :dateFrom AND a.startDateTime <= :dateTill
                AND m.activityId NOT IN(:excludedActivityIds)';

        $params = [
            'streamType' => StreamType::WATTS->value,
            'metricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
            'sportTypes' => $sportTypes->map(fn (SportType $sportType) => $sportType->value),
            'dateFrom' => $dateRange->getFrom()->format('Y-m-d 00:00:00'),
            'dateTill' => $dateRange->getTill()->format('Y-m-d 23:59:59'),
            'excludedActivityIds' => $this->settingsRepository->metrics()->getActivitiesExcludedFromPeakPowerOutputs()->map(fn (ActivityId $activityId): string => (string) $activityId) ?: ['unexisting-id'],
        ];
        $types = [
            'sportTypes' => ArrayParameterType::STRING,
            'excludedActivityIds' => ArrayParameterType::STRING,
        ];

        $results = $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $athleteWeightHistory = $this->settingsRepository->general()->getAthleteWeightHistory($this->settingsRepository->appearance()->getUnitSystem());

        /** @var array<int, array{activityId: string, power: int}> $bestPerInterval */
        $bestPerInterval = [];
        foreach ($results as $result) {
            $bestAverages = Json::uncompressAndDecode($result['data']);
            foreach (self::TIME_INTERVALS_IN_SECONDS_ALL as $timeIntervalInSeconds) {
                if (!isset($bestAverages[$timeIntervalInSeconds])) {
                    continue;
                }
                $power = $bestAverages[$timeIntervalInSeconds];
                if (!isset($bestPerInterval[$timeIntervalInSeconds]) || $power > $bestPerInterval[$timeIntervalInSeconds]['power']) {
                    $bestPerInterval[$timeIntervalInSeconds] = [
                        'activityId' => $result['activityId'],
                        'power' => $power,
                    ];
                }
            }
        }

        foreach ($bestPerInterval as $timeIntervalInSeconds => $best) {
            $activityId = ActivityId::fromString($best['activityId']);
            $activitySummary = $this->activitySummaryRepository->find($activityId);
            $interval = CarbonInterval::seconds($timeIntervalInSeconds);

            try {
                $athleteWeight = $athleteWeightHistory->find($activitySummary->getStartDate())->getWeightInKg();
            } catch (EntityNotFound) {
                continue; // @codeCoverageIgnore
            }

            $relativePower = $athleteWeight->toFloat() > 0 ? round($best['power'] / $athleteWeight->toFloat(), 2) : 0;
            $powerOutputs->add(
                PowerOutput::fromState(
                    timeIntervalInSeconds: $timeIntervalInSeconds,
                    formattedTimeInterval: 0 !== (int) $interval->totalHours ? $interval->totalHours.' h' : (0 !== (int) $interval->totalMinutes ? $interval->totalMinutes.' m' : $interval->totalSeconds.' s'),
                    power: $best['power'],
                    relativePower: $relativePower,
                    activityId: $activityId,
                )
            );
        }

        return $powerOutputs;
    }
}

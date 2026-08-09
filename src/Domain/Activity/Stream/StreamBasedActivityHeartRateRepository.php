<?php

declare(strict_types=1);

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricRepository;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Athlete\HeartRateZone\HeartRateZone;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZones;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class StreamBasedActivityHeartRateRepository implements ActivityHeartRateRepository
{
    private const int CALCULATE_HEART_RATE_ZONES_FOR_LAST_X_DAYS = 30;

    public function __construct(
        private Connection $connection,
        private ActivitySummaryRepository $activitySummaryRepository,
        private ActivityStreamMetricRepository $activityStreamMetricRepository,
        private Clock $clock,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function findTotalTimeInSecondsInHeartRateZonesForActivity(ActivityId $activityId): TimeInHeartRateZones
    {
        $activitySummary = $this->activitySummaryRepository->find($activityId);

        $heartRateDistribution = $this->activityStreamMetricRepository
            ->findByActivityIdAndMetricType($activityId, ActivityStreamMetricType::VALUE_DISTRIBUTION)
            ->filterOnStreamType(StreamType::HEART_RATE)?->getData() ?? [];

        if ([] === $heartRateDistribution) {
            throw new EntityNotFound(sprintf('HeartRateZones for "%s" not found', $activityId));
        }

        return $this->toTimeInHeartRateZones($this->zoneSecondsFor(
            sportType: $activitySummary->getSportType(),
            on: $activitySummary->getStartDate(),
            heartRateDistribution: $heartRateDistribution,
        ));
    }

    public function findTotalTimeInSecondsInHeartRateZones(): TimeInHeartRateZones
    {
        return $this->toTimeInHeartRateZones($this->aggregate()->getTotal());
    }

    /**
     * @return array<string, TimeInHeartRateZones>
     */
    public function findTotalTimeInSecondsInHeartRateZonesPerActivityType(): array
    {
        return array_map(
            $this->toTimeInHeartRateZones(...),
            $this->aggregate()->getPerActivityType()
        );
    }

    public function findTotalTimeInSecondsInHeartRateZonesForLast30Days(): TimeInHeartRateZones
    {
        return $this->toTimeInHeartRateZones($this->aggregate()->getInLastXDays());
    }

    private function aggregate(): HeartRateZoneTotals
    {
        $cutOffDate = $this->clock->getCurrentDateTimeImmutable()->sub(
            \DateInterval::createFromDateString(self::CALCULATE_HEART_RATE_ZONES_FOR_LAST_X_DAYS.' days')
        );

        $total = $this->emptyZones();
        $inLastXDays = $this->emptyZones();
        $perActivityType = array_combine(
            array_map(fn (ActivityType $activityType): string => $activityType->value, ActivityType::cases()),
            array_map(fn (ActivityType $activityType): array => $this->emptyZones(), ActivityType::cases()),
        );

        $results = $this->connection->executeQuery(
            'SELECT a.startDateTime, a.sportType, m.data
             FROM ActivityStreamMetric m
             INNER JOIN Activity a ON a.activityId = m.activityId
             WHERE m.streamType = :streamType AND m.metricType = :metricType',
            [
                'streamType' => StreamType::HEART_RATE->value,
                'metricType' => ActivityStreamMetricType::VALUE_DISTRIBUTION->value,
            ]
        )->fetchAllAssociative();

        foreach ($results as $result) {
            $heartRateDistribution = Json::uncompressAndDecode($result['data']);
            if ([] === $heartRateDistribution) {
                continue;
            }

            $sportType = SportType::from($result['sportType']);
            $startDate = SerializableDateTime::fromString($result['startDateTime']);
            $zoneSeconds = $this->zoneSecondsFor(
                sportType: $sportType,
                on: $startDate,
                heartRateDistribution: $heartRateDistribution,
            );

            $activityType = $sportType->getActivityType();
            foreach ($zoneSeconds as $zoneName => $secondsInZone) {
                $total[$zoneName] += $secondsInZone;
                $perActivityType[$activityType->value][$zoneName] += $secondsInZone;

                if ($startDate->isAfterOrOn($cutOffDate)) {
                    $inLastXDays[$zoneName] += $secondsInZone;
                }
            }
        }

        return HeartRateZoneTotals::fromState(
            total: $total,
            perActivityType: $perActivityType,
            inLastXDays: $inLastXDays,
        );
    }

    /**
     * @param array<int|string, int> $heartRateDistribution
     *
     * @return array<string, int>
     */
    private function zoneSecondsFor(SportType $sportType, SerializableDateTime $on, array $heartRateDistribution): array
    {
        $general = $this->settingsRepository->general();
        $athleteMaxHeartRate = $general->getAthlete()->getMaxHeartRate($on);
        $athleteHeartRateZones = $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
            sportType: $sportType,
            on: $on
        );

        $zoneSeconds = $this->emptyZones();
        foreach ($athleteHeartRateZones->getZones() as $heartRateZone) {
            [$minHeartRate, $maxHeartRate] = $heartRateZone->getRangeInBpm($athleteMaxHeartRate);

            foreach ($heartRateDistribution as $heartRate => $numberOfSamples) {
                if ($heartRate >= $minHeartRate && $heartRate <= $maxHeartRate) {
                    $zoneSeconds[$heartRateZone->getName()] += $numberOfSamples;
                }
            }
        }

        return $zoneSeconds;
    }

    /**
     * @param array<string, int> $zoneSeconds
     */
    private function toTimeInHeartRateZones(array $zoneSeconds): TimeInHeartRateZones
    {
        return TimeInHeartRateZones::create(
            timeInZoneOne: $zoneSeconds[HeartRateZone::ONE],
            timeInZoneTwo: $zoneSeconds[HeartRateZone::TWO],
            timeInZoneThree: $zoneSeconds[HeartRateZone::THREE],
            timeInZoneFour: $zoneSeconds[HeartRateZone::FOUR],
            timeInZoneFive: $zoneSeconds[HeartRateZone::FIVE],
        );
    }

    /**
     * @return array<string, int>
     */
    private function emptyZones(): array
    {
        return [
            HeartRateZone::ONE => 0,
            HeartRateZone::TWO => 0,
            HeartRateZone::THREE => 0,
            HeartRateZone::FOUR => 0,
            HeartRateZone::FIVE => 0,
        ];
    }
}

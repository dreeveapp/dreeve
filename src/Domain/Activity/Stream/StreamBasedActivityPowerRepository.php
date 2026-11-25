<?php

namespace App\Domain\Activity\Stream;

use App\Domain\Activity\ActivityExclusion;
use App\Domain\Activity\ActivityId;
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

final readonly class StreamBasedActivityPowerRepository implements ActivityPowerRepository
{
    public function __construct(
        private Connection $connection,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function findBestForSportTypes(SportTypes $sportTypes): PowerOutputs
    {
        return $this->findBestForSportTypesPerDateRange(
            sportTypes: $sportTypes,
            dateRanges: [
                'allTime' => DateRange::fromDates(
                    from: SerializableDateTime::fromString('1970-01-01 00:00:00'),
                    till: SerializableDateTime::fromString('2100-01-01 00:00:00')
                ),
            ],
        )['allTime'];
    }

    public function findBestForSportTypesPerDateRange(SportTypes $sportTypes, array $dateRanges): array
    {
        $sql = 'SELECT m.activityId, m.data, a.startDateTime FROM ActivityStreamMetric m
                INNER JOIN Activity a ON a.activityId = m.activityId
                WHERE m.streamType = :streamType
                AND m.metricType = :metricType
                AND a.sportType IN(:sportTypes)
                AND NOT EXISTS (SELECT 1 FROM json_each(a.excludedFrom) WHERE json_each.value = :excludedFrom)';

        $params = [
            'streamType' => StreamType::WATTS->value,
            'metricType' => ActivityStreamMetricType::BEST_AVERAGES->value,
            'sportTypes' => $sportTypes->toValues(),
            'excludedFrom' => ActivityExclusion::PEAK_POWER_OUTPUTS->value,
        ];
        $types = [
            'sportTypes' => ArrayParameterType::STRING,
        ];

        $results = $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();

        $athleteWeightHistory = $this->settingsRepository->athleteWeight()->getHistory($this->settingsRepository->appearance()->getUnitSystem());

        $dateBoundsPerRange = array_map(
            fn (DateRange $dateRange): array => [
                'from' => $dateRange->getFrom()->format('Y-m-d 00:00:00'),
                'till' => $dateRange->getTill()->format('Y-m-d 23:59:59'),
            ],
            $dateRanges,
        );

        /** @var array<string, array<int, array{activityId: string, startDateTime: string, power: int}>> $bestPerIntervalPerRange */
        $bestPerIntervalPerRange = array_map(fn (): array => [], $dateRanges);
        foreach ($results as $result) {
            $bestAverages = Json::uncompressAndDecode($result['data']);
            foreach ($dateBoundsPerRange as $rangeKey => $dateBounds) {
                if ($result['startDateTime'] < $dateBounds['from'] || $result['startDateTime'] > $dateBounds['till']) {
                    continue;
                }
                foreach (self::TIME_INTERVALS_IN_SECONDS_ALL as $timeIntervalInSeconds) {
                    if (!isset($bestAverages[$timeIntervalInSeconds])) {
                        continue;
                    }
                    $power = $bestAverages[$timeIntervalInSeconds];
                    if (!isset($bestPerIntervalPerRange[$rangeKey][$timeIntervalInSeconds]) || $power > $bestPerIntervalPerRange[$rangeKey][$timeIntervalInSeconds]['power']) {
                        $bestPerIntervalPerRange[$rangeKey][$timeIntervalInSeconds] = [
                            'activityId' => $result['activityId'],
                            'startDateTime' => $result['startDateTime'],
                            'power' => $power,
                        ];
                    }
                }
            }
        }

        $powerOutputsPerRange = [];
        foreach ($bestPerIntervalPerRange as $rangeKey => $bestPerInterval) {
            $powerOutputs = PowerOutputs::empty();
            foreach ($bestPerInterval as $timeIntervalInSeconds => $best) {
                $activityId = ActivityId::fromString($best['activityId']);
                $interval = CarbonInterval::seconds($timeIntervalInSeconds);

                $athleteWeight = null;
                try {
                    $athleteWeight = $athleteWeightHistory->find(SerializableDateTime::fromString($best['startDateTime']))->getWeightInKg();
                } catch (EntityNotFound) {
                }

                $relativePower = $athleteWeight?->toFloat() > 0 ? round($best['power'] / $athleteWeight->toFloat(), 2) : null;
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
            $powerOutputsPerRange[$rangeKey] = $powerOutputs;
        }

        return $powerOutputsPerRange;
    }
}

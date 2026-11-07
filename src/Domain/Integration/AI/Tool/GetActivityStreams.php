<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\ActivitySummaryRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Activity\Stream\ActivityStream;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;

final readonly class GetActivityStreams implements AITool
{
    public function __construct(
        private ActivitySummaryRepository $activitySummaryRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_activity_streams';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves aggregated stream statistics for a specific activity using its unique activity ID.
            Use this tool when the user asks about heart rate, power or speed ranges within an activity, or about the time spent in each heart rate zone.
            It requires the activity ID as input and returns, per available stream, the number of recorded points plus the minimum, maximum and average value.
            It also returns the time spent in each heart rate zone, together with the bpm range of each zone as configured on the day of the activity.
            It does NOT return the raw time-series, so it cannot be used to analyse how a value evolved over the course of the activity.
            Example requests include “What was my average and max power on activity 12345?” or “How long was I in zone 2 on activity 12345?”
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('activityId', 'The id of the activity.', required: true),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $activityId = $arguments->getActivityId();

        try {
            $activity = $this->activitySummaryRepository->find($activityId);
        } catch (EntityNotFound) {
            throw new ToolFailed(sprintf('Activity "%s" does not exist.', $activityId));
        }

        $timeInHeartRateZones = null;
        try {
            $timeInZones = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesForActivity($activityId);
            $general = $this->settingsRepository->general();
            $maxHeartRate = $general->getAthlete()->getMaxHeartRate($activity->getStartDate());
            $heartRateZones = $general->getHeartRateZoneConfiguration()->getHeartRateZonesFor(
                sportType: $activity->getSportType(),
                on: $activity->getStartDate(),
            );

            $timeInHeartRateZones = [
                'zone1' => [
                    'rangeInBpm' => $heartRateZones->getZoneOne()->getRangeInBpm($maxHeartRate),
                    'timeInSeconds' => $timeInZones->getTimeInZoneOne(),
                    'percentage' => $timeInZones->getPercentageInZoneOne(),
                ],
                'zone2' => [
                    'rangeInBpm' => $heartRateZones->getZoneTwo()->getRangeInBpm($maxHeartRate),
                    'timeInSeconds' => $timeInZones->getTimeInZoneTwo(),
                    'percentage' => $timeInZones->getPercentageInZoneTwo(),
                ],
                'zone3' => [
                    'rangeInBpm' => $heartRateZones->getZoneThree()->getRangeInBpm($maxHeartRate),
                    'timeInSeconds' => $timeInZones->getTimeInZoneThree(),
                    'percentage' => $timeInZones->getPercentageInZoneThree(),
                ],
                'zone4' => [
                    'rangeInBpm' => $heartRateZones->getZoneFour()->getRangeInBpm($maxHeartRate),
                    'timeInSeconds' => $timeInZones->getTimeInZoneFour(),
                    'percentage' => $timeInZones->getPercentageInZoneFour(),
                ],
                'zone5' => [
                    'rangeInBpm' => $heartRateZones->getZoneFive()->getRangeInBpm($maxHeartRate),
                    'timeInSeconds' => $timeInZones->getTimeInZoneFive(),
                    'percentage' => $timeInZones->getPercentageInZoneFive(),
                ],
            ];
        } catch (EntityNotFound) {
        }

        return [
            'streams' => array_values(array_filter($this->activityStreamRepository
                ->findByActivityId($activityId)
                ->map(static fn (ActivityStream $stream): array => $stream->exportForAITooling()))),
            'timeInHeartRateZones' => $timeInHeartRateZones,
        ];
    }
}

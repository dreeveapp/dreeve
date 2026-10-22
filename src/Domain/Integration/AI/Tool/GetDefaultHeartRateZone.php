<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Time\Clock\Clock;

final readonly class GetDefaultHeartRateZone implements AITool
{
    public function __construct(
        private Clock $clock,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'get_heart_rate_zones';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Retrieves the athlete’s personalized heart rate zones from the database, including ranges based on the athlete’s maximum heart rate or custom configuration.
            You can optionally provide a sportType to fetch zones specific to a particular sport.
            Use this tool when the user asks about heart rate zones, training intensity, or zone-based performance. If a sportType is specified, the zones returned will correspond to that sport.
            It provides the data needed to interpret workouts, plan training, or summarize effort by zone.
            Example requests include “Show my heart rate zones” or “What zone was I in during my last ride?”
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('sportType', 'The sport type to get the heart rate zones for.'),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $now = $this->clock->getCurrentDateTimeImmutable();
        $sportType = SportType::tryFrom($arguments->getString('sportType') ?? '');
        $general = $this->settingsRepository->general();
        $heartRateZones = $general->getHeartRateZoneConfiguration()->getDefaultHeartRateZones($sportType);
        $maxHeartRate = $general->getAthlete()->getMaxHeartRate($now);

        return [
            'zone1' => $heartRateZones->getZoneOne()->getRangeInBpm($maxHeartRate),
            'zone2' => $heartRateZones->getZoneTwo()->getRangeInBpm($maxHeartRate),
            'zone3' => $heartRateZones->getZoneThree()->getRangeInBpm($maxHeartRate),
            'zone4' => $heartRateZones->getZoneFour()->getRangeInBpm($maxHeartRate),
            'zone5' => $heartRateZones->getZoneFive()->getRangeInBpm($maxHeartRate),
        ];
    }
}

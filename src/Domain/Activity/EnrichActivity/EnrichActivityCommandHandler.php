<?php

declare(strict_types=1);

namespace App\Domain\Activity\EnrichActivity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Integration\Geocoding\Nominatim\CouldNotReverseGeocodeAddress;
use App\Domain\Integration\Geocoding\Nominatim\Nominatim;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoArchiveApiCallHasFailed;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoForecastApiCallHasFailed;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\ValueObject\Geography\Coordinate;

final readonly class EnrichActivityCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private Nominatim $nominatim,
        private OpenMeteo $openMeteo,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof EnrichActivity);

        $activityWithRawData = $this->activityRepository->findWithRawData($command->getActivityId());
        $activity = $activityWithRawData->getActivity();
        $startingCoordinate = $activity->getStartingCoordinate();

        if (!$startingCoordinate instanceof Coordinate) {
            return;
        }
        if (!$activity->isMissingReverseGeocoding() && !$activity->isMissingWeather()) {
            return;
        }

        if ($activity->isMissingReverseGeocoding()) {
            try {
                $activity = $activity->withRouteGeography($activity->getRouteGeography()->updateWith(
                    $this->nominatim->reverseGeocode($startingCoordinate)
                ));
            } catch (CouldNotReverseGeocodeAddress) {
                throw CouldNotProcessCommand::withReason('Could not reach Nominatim to reverse geocode the location of this activity.');
            }
        }

        if ($activity->isMissingWeather()) {
            try {
                $weather = Weather::fromRawData(
                    $this->openMeteo->getWeatherStats(
                        coordinate: $startingCoordinate,
                        date: $activity->getStartDate()
                    ),
                    on: $activity->getStartDate()
                );
                if ($weather instanceof Weather) {
                    $activity = $activity->withWeather($weather);
                }
            } catch (OpenMeteoForecastApiCallHasFailed|OpenMeteoArchiveApiCallHasFailed) {
                throw CouldNotProcessCommand::withReason('Could not reach Open-Meteo to look up the weather for this activity.');
            }
        }

        $this->activityRepository->update(ActivityWithRawData::fromState(
            activity: $activity,
            rawData: $activityWithRawData->getRawData(),
        ));
    }
}

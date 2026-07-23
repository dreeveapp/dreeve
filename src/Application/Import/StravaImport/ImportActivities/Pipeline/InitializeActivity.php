<?php

namespace App\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Measurement\Velocity\MetersPerSecond;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
final readonly class InitializeActivity implements ActivityImportStep
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private GearRepository $gearRepository,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activityId = $context->getActivityId();
        $rawStravaData = $context->getRawStravaData();

        $sportType = SportType::from($rawStravaData['sport_type']);

        try {
            $activity = $this->activityRepository->find($activityId);
            $gearId = GearId::fromOptionalUnprefixed($rawStravaData['gear_id'] ?? null);
            if (!$gearId instanceof GearId
                && ($currentGearId = $activity->getGearId()) instanceof GearId && $this->isCustomGear($currentGearId)) {
                // Custom gear does not exist in Strava. When the Strava payload does not reference
                // any gear, keep the manual assignment instead of emptying it. A gear assigned
                // in Strava always wins.
                $gearId = $currentGearId;
            }

            $activity = $activity
                ->withName(ActivityName::fromString($rawStravaData['name']))
                ->withSportType($sportType)
                ->withDistance(Kilometer::from(round($rawStravaData['distance'] / 1000, 3)))
                ->withAverageSpeed(MetersPerSecond::from($rawStravaData['average_speed'])->toKmPerHour())
                ->withMaxSpeed(MetersPerSecond::from($rawStravaData['max_speed'])->toKmPerHour())
                ->withMovingTimeInSeconds($rawStravaData['moving_time'] ?? 0)
                ->withElevation(Meter::from($rawStravaData['total_elevation_gain']))
                ->withStartingCoordinate(Coordinate::createFromOptionalLatAndLng(
                    Latitude::fromOptionalString($rawStravaData['start_latlng'][0] ?? null),
                    Longitude::fromOptionalString($rawStravaData['start_latlng'][1] ?? null),
                ))
                ->withPolyline($rawStravaData['map']['summary_polyline'] ?? null)
                ->withGear($gearId)
                ->withWorkoutType(WorkoutType::fromStravaInt($rawStravaData['workout_type'] ?? null));

            if (array_key_exists('commute', $rawStravaData)) {
                $activity = $activity->withCommute($rawStravaData['commute']);
            }

            return $context->withActivity($activity);
        } catch (EntityNotFound) {
        }

        $activity = Activity::createFromRawStravaData($rawStravaData);

        return $context->withActivity($activity);
    }

    private function isCustomGear(GearId $gearId): bool
    {
        try {
            return $this->gearRepository->find($gearId)->getType()->isCustom();
        } catch (EntityNotFound) {
            return false;
        }
    }
}

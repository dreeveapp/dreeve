<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityName;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Domain\Activity\WorldType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final class ActivityBuilder
{
    private ActivityId $activityId;
    private SerializableDateTime $startDateTime;
    private SportType $sportType = SportType::RIDE;
    private WorldType $worldType = WorldType::REAL_WORLD;
    private ImportSource $importSource = ImportSource::STRAVA_API;
    private ExternalReferenceId $externalReferenceId;
    private ActivityName $name;
    private readonly string $description;
    private Kilometer $distance;
    private Meter $elevation;
    private ?Coordinate $startingCoordinate = null;
    private int $calories;
    private ?int $averagePower = null;
    private ?int $maxPower;
    private KmPerHour $averageSpeed;
    private KmPerHour $maxSpeed;
    private ?int $averageHeartRate = null;
    private ?int $maxHeartRate;
    private ?int $averageCadence;
    private int $movingTimeInSeconds = 10;
    private int $elapsedTimeInSeconds = 10;
    private int $totalImageCount = 0;
    private ?string $deviceName = null;
    /** @var array<string> */
    private array $localImagePaths = [];
    private ?string $polyline = null;
    private RouteGeography $routeGeography;
    private readonly ?string $weather;
    private ?GearId $gearId = null;
    private bool $isCommute;
    private ?WorkoutType $workoutType;

    private function __construct()
    {
        $this->activityId = ActivityId::fromUnprefixed('903645');
        $this->startDateTime = SerializableDateTime::fromString('2023-10-10');
        $this->name = ActivityName::fromString('Test activity');
        $this->description = '';
        $this->distance = Kilometer::from(10);
        $this->elevation = Meter::from(0);
        $this->calories = 0;
        $this->maxPower = null;
        $this->averageSpeed = KmPerHour::from(0);
        $this->maxSpeed = KmPerHour::from(0);
        $this->maxHeartRate = null;
        $this->averageCadence = null;
        $this->weather = null;
        $this->routeGeography = RouteGeography::create([]);
        $this->isCommute = false;
        $this->workoutType = null;
        $this->externalReferenceId = ExternalReferenceId::fromString('1234567');
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): Activity
    {
        return Activity::fromState(
            activityId: $this->activityId,
            startDateTime: $this->startDateTime,
            sportType: $this->sportType,
            worldType: $this->worldType,
            importSource: $this->importSource,
            externalReferenceId: $this->externalReferenceId,
            name: $this->name,
            description: $this->description,
            distance: $this->distance,
            elevation: $this->elevation,
            startingCoordinate: $this->startingCoordinate,
            calories: $this->calories,
            kilojoules: null,
            averagePower: $this->averagePower,
            maxPower: $this->maxPower,
            averageSpeed: $this->averageSpeed,
            maxSpeed: $this->maxSpeed,
            averageHeartRate: $this->averageHeartRate,
            maxHeartRate: $this->maxHeartRate,
            averageCadence: $this->averageCadence,
            movingTimeInSeconds: $this->movingTimeInSeconds,
            elapsedTimeInSeconds: $this->elapsedTimeInSeconds,
            deviceName: $this->deviceName,
            totalImageCount: $this->totalImageCount,
            localImagePaths: $this->localImagePaths,
            polyline: $this->polyline,
            routeGeography: $this->routeGeography,
            weather: $this->weather,
            gearId: $this->gearId,
            isCommute: $this->isCommute,
            workoutType: $this->workoutType,
        );
    }

    public function withActivityId(ActivityId $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = ActivityName::fromString($name);

        return $this;
    }

    public function withStartDateTime(SerializableDateTime $startDateTime): self
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function withAveragePower(int $averagePower): self
    {
        $this->averagePower = $averagePower;

        return $this;
    }

    public function withMovingTimeInSeconds(int $movingTimeInSeconds): self
    {
        $this->movingTimeInSeconds = $movingTimeInSeconds;

        return $this;
    }

    public function withCalories(int $calories): self
    {
        $this->calories = $calories;

        return $this;
    }

    public function withElapsedTimeInSeconds(int $elapsedTimeInSeconds): self
    {
        $this->elapsedTimeInSeconds = $elapsedTimeInSeconds;

        return $this;
    }

    public function withAverageHeartRate(int $averageHeartRate): self
    {
        $this->averageHeartRate = $averageHeartRate;

        return $this;
    }

    public function withGearId(GearId $gearId): self
    {
        $this->gearId = $gearId;

        return $this;
    }

    public function withoutGearId(): self
    {
        $this->gearId = null;

        return $this;
    }

    public function withRouteGeography(RouteGeography $routeGeography): self
    {
        $this->routeGeography = $routeGeography;

        return $this;
    }

    public function withStartingCoordinate(Coordinate $coordinate): self
    {
        $this->startingCoordinate = $coordinate;

        return $this;
    }

    public function withDeviceName(string $deviceName): self
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function withoutDeviceName(): self
    {
        $this->deviceName = null;

        return $this;
    }

    public function withIsCommute(bool $isCommute): self
    {
        $this->isCommute = $isCommute;

        return $this;
    }

    public function withSportType(SportType $sportType): self
    {
        $this->sportType = $sportType;

        return $this;
    }

    public function withPolyline(?string $polyline): self
    {
        $this->polyline = $polyline;

        return $this;
    }

    public function withTotalImageCount(int $totalImageCount): self
    {
        $this->totalImageCount = $totalImageCount;

        return $this;
    }

    public function withDistance(Kilometer $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function withElevation(Meter $elevation): self
    {
        $this->elevation = $elevation;

        return $this;
    }

    public function withLocalImagePaths(string ...$localImagePaths): self
    {
        $this->localImagePaths = $localImagePaths;

        return $this;
    }

    public function withoutLocalImagePaths(): self
    {
        $this->localImagePaths = [];

        return $this;
    }

    public function withWorldType(WorldType $worldType): self
    {
        $this->worldType = $worldType;

        return $this;
    }

    public function withImportSource(ImportSource $importSource): self
    {
        $this->importSource = $importSource;

        return $this;
    }

    public function withExternalReferenceId(?ExternalReferenceId $externalReferenceId): self
    {
        $this->externalReferenceId = $externalReferenceId;

        return $this;
    }

    public function withMaxPower(int $maxPower): self
    {
        $this->maxPower = $maxPower;

        return $this;
    }

    public function withMaxSpeed(KmPerHour $maxSpeed): self
    {
        $this->maxSpeed = $maxSpeed;

        return $this;
    }

    public function withMaxHeartRate(int $maxHeartRate): self
    {
        $this->maxHeartRate = $maxHeartRate;

        return $this;
    }

    public function withAverageCadence(int $averageCadence): self
    {
        $this->averageCadence = $averageCadence;

        return $this;
    }

    public function withWorkoutType(WorkoutType $workoutType): self
    {
        $this->workoutType = $workoutType;

        return $this;
    }

    public function withAverageSpeed(KmPerHour $averageSpeed): self
    {
        $this->averageSpeed = $averageSpeed;

        return $this;
    }
}

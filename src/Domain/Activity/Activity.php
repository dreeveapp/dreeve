<?php

namespace App\Domain\Activity;

use App\Domain\Activity\Route\RouteGeography;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Gear\RecordingDevice\RecordingDeviceId;
use App\Domain\Gear\Sensor\ConnectedSensors;
use App\Domain\Integration\Weather\OpenMeteo\Weather;
use App\Domain\Zwift\CouldNotDetermineZwiftMap;
use App\Domain\Zwift\ZwiftMap;
use App\Infrastructure\Eventing\RecordsEvents;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Length\NauticalMile;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Measurement\Velocity\Knot;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use App\Infrastructure\Measurement\Velocity\SecPer100Meter;
use App\Infrastructure\Measurement\Velocity\SecPerKm;
use App\Infrastructure\Serialization\Escape;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\SerializableTimezone;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'Activity_startDateTimeIndex', columns: ['startDateTime'])]
#[ORM\Index(name: 'Activity_sportType', columns: ['sportType'])]
#[ORM\Index(name: 'Activity_gearId', columns: ['gearId'])]
#[ORM\Index(name: 'Activity_gearIdStartDateTime', columns: ['gearId', 'startDateTime'])]
#[ORM\Index(name: 'Activity_markedForDeletion', columns: ['markedForDeletion'])]
#[ORM\Index(name: 'Activity_streamsAreImported', columns: ['streamsAreImported'])]
#[ORM\Index(name: 'Activity_importSource', columns: ['importSource'])]
final class Activity
{
    use RecordsEvents;
    use ProvideTimeFormats;

    public const string DATE_TIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    #[ORM\Column(type: 'string', nullable: true)]
    // @phpstan-ignore-next-line
    private ActivityType $activityType;
    #[ORM\Column(type: 'json', nullable: true)]
    // @phpstan-ignore-next-line
    private readonly array $data;
    #[ORM\Column(type: 'boolean', nullable: true)]
    // @phpstan-ignore-next-line
    private readonly bool $streamsAreImported;
    #[ORM\Column(type: 'boolean', nullable: true)]
    // @phpstan-ignore-next-line
    private readonly bool $markedForDeletion;

    /**
     * @param array<string> $localImagePaths
     */
    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private readonly ActivityId $activityId,
        #[ORM\Column(type: 'datetime_immutable')]
        private readonly SerializableDateTime $startDateTime,
        #[ORM\Column(type: 'string')]
        private readonly SportType $sportType,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly WorldType $worldType,
        #[ORM\Column(type: 'string', options: ['default' => ImportSource::STRAVA_API->value])]
        private readonly ImportSource $importSource,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?ExternalReferenceId $externalReferenceId,
        #[ORM\Column(type: 'string')]
        private readonly ActivityName $name,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?string $description,
        #[ORM\Column(type: 'integer')]
        private readonly Kilometer $distance,
        #[ORM\Column(type: 'integer')]
        private readonly Meter $elevation,
        #[ORM\Embedded(class: Coordinate::class)]
        private readonly ?Coordinate $startingCoordinate,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $calories,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $kilojoules,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $averagePower,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $maxPower,
        #[ORM\Column(type: 'float')]
        private readonly KmPerHour $averageSpeed,
        #[ORM\Column(type: 'float')]
        private readonly KmPerHour $maxSpeed,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $averageHeartRate,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $maxHeartRate,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly ?int $averageCadence,
        #[ORM\Column(type: 'integer')]
        private readonly int $movingTimeInSeconds,
        #[ORM\Column(type: 'integer', nullable: true)]
        private readonly int $elapsedTimeInSeconds,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?string $deviceName,
        #[ORM\Column(type: 'json', nullable: true)]
        private readonly ?ConnectedSensors $connectedSensors,
        #[ORM\Column(type: 'integer')]
        private readonly int $totalImageCount,
        #[ORM\Column(type: 'text', nullable: true)]
        private readonly array $localImagePaths,
        #[ORM\Column(type: 'text', nullable: true)]
        private readonly ?string $polyline,
        #[ORM\Column(type: 'json', nullable: true)]
        private readonly RouteGeography $routeGeography,
        #[ORM\Column(type: 'json', nullable: true)]
        private readonly ?string $weather,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?GearId $gearId,
        #[ORM\Column(type: 'boolean', nullable: true)]
        private readonly bool $isCommute,
        #[ORM\Column(type: 'string', nullable: true)]
        private readonly ?WorkoutType $workoutType,
    ) {
    }

    /**
     * @param array<mixed> $rawData
     */
    public static function createFromRawStravaData(array $rawData): self
    {
        $startDate = SerializableDateTime::createFromFormat(
            format: Activity::DATE_TIME_FORMAT,
            datetime: $rawData['start_date_local'],
            timezone: SerializableTimezone::default(),
        );

        $deviceName = $rawData['device_name'] ?? null;

        return self::create(
            activityId: ActivityId::fromUnprefixed((string) $rawData['id']),
            startDateTime: $startDate,
            sportType: SportType::from($rawData['sport_type']),
            worldType: WorldType::fromDeviceAndActivityName($deviceName, $rawData['name'] ?? ''),
            importSource: ImportSource::STRAVA_API,
            externalReferenceId: ExternalReferenceId::fromOptionalString($rawData['external_id'] ?? ''),
            name: ActivityName::fromString($rawData['name']),
            description: $rawData['description'],
            distance: Kilometer::from(round($rawData['distance'] / 1000, 3)),
            elevation: Meter::from(round($rawData['total_elevation_gain'])),
            startingCoordinate: Coordinate::createFromOptionalLatAndLng(
                Latitude::fromOptionalString($rawData['start_latlng'][0] ?? null),
                Longitude::fromOptionalString($rawData['start_latlng'][1] ?? null),
            ),
            calories: (int) ($rawData['calories'] ?? 0),
            kilojoules: isset($rawData['kilojoules']) ? (int) $rawData['kilojoules'] : null,
            averagePower: isset($rawData['average_watts']) ? (int) $rawData['average_watts'] : null,
            maxPower: isset($rawData['max_watts']) ? (int) $rawData['max_watts'] : null,
            averageSpeed: MetersPerSecond::from($rawData['average_speed'])->toKmPerHour(),
            maxSpeed: MetersPerSecond::from($rawData['max_speed'])->toKmPerHour(),
            averageHeartRate: isset($rawData['average_heartrate']) ? (int) round($rawData['average_heartrate']) : null,
            maxHeartRate: isset($rawData['max_heartrate']) ? (int) round($rawData['max_heartrate']) : null,
            averageCadence: isset($rawData['average_cadence']) ? (int) round($rawData['average_cadence']) : null,
            movingTimeInSeconds: $rawData['moving_time'] ?? 0,
            elapsedTimeInSeconds: $rawData['elapsed_time'] ?? 0,
            deviceName: $deviceName,
            connectedSensors: null,
            totalImageCount: $rawData['total_photo_count'] ?? 0,
            localImagePaths: [],
            polyline: $rawData['map']['summary_polyline'] ?? null,
            routeGeography: RouteGeography::create([]),
            weather: null,
            gearId: GearId::fromOptionalUnprefixed($rawData['gear_id'] ?? null),
            isCommute: $rawData['commute'] ?? false,
            workoutType: WorkoutType::fromStravaInt($rawData['workout_type'] ?? null),
        );
    }

    /**
     * @param array<string> $localImagePaths
     */
    public static function create(
        ActivityId $activityId,
        SerializableDateTime $startDateTime,
        SportType $sportType,
        WorldType $worldType,
        ImportSource $importSource,
        ?ExternalReferenceId $externalReferenceId,
        ActivityName $name,
        ?string $description,
        Kilometer $distance,
        Meter $elevation,
        ?Coordinate $startingCoordinate,
        ?int $calories,
        ?int $kilojoules,
        ?int $averagePower,
        ?int $maxPower,
        KmPerHour $averageSpeed,
        KmPerHour $maxSpeed,
        ?int $averageHeartRate,
        ?int $maxHeartRate,
        ?int $averageCadence,
        int $movingTimeInSeconds,
        int $elapsedTimeInSeconds,
        ?string $deviceName,
        ?ConnectedSensors $connectedSensors,
        int $totalImageCount,
        array $localImagePaths,
        ?string $polyline,
        RouteGeography $routeGeography,
        ?string $weather,
        ?GearId $gearId,
        bool $isCommute,
        ?WorkoutType $workoutType,
    ): self {
        $activity = self::fromState(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: $worldType,
            importSource: $importSource,
            externalReferenceId: $externalReferenceId,
            name: $name,
            description: $description,
            distance: $distance,
            elevation: $elevation,
            startingCoordinate: $startingCoordinate,
            calories: $calories,
            kilojoules: $kilojoules,
            averagePower: $averagePower,
            maxPower: $maxPower,
            averageSpeed: $averageSpeed,
            maxSpeed: $maxSpeed,
            averageHeartRate: $averageHeartRate,
            maxHeartRate: $maxHeartRate,
            averageCadence: $averageCadence,
            movingTimeInSeconds: $movingTimeInSeconds,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            deviceName: $deviceName,
            connectedSensors: $connectedSensors,
            totalImageCount: $totalImageCount,
            localImagePaths: $localImagePaths,
            polyline: $polyline,
            routeGeography: $routeGeography,
            weather: $weather,
            gearId: $gearId,
            isCommute: $isCommute,
            workoutType: $workoutType,
        );
        $activity->recordThat(new ActivityWasAdded($activity->getStartDate()));
        if ($activity->hasMappableRoute()) {
            $activity->recordThat(new ActivityRouteWasUpdated());
        }

        return $activity;
    }

    /**
     * @param array<string> $localImagePaths
     */
    public static function fromState(
        ActivityId $activityId,
        SerializableDateTime $startDateTime,
        SportType $sportType,
        WorldType $worldType,
        ImportSource $importSource,
        ?ExternalReferenceId $externalReferenceId,
        ActivityName $name,
        ?string $description,
        Kilometer $distance,
        Meter $elevation,
        ?Coordinate $startingCoordinate,
        ?int $calories,
        ?int $kilojoules,
        ?int $averagePower,
        ?int $maxPower,
        KmPerHour $averageSpeed,
        KmPerHour $maxSpeed,
        ?int $averageHeartRate,
        ?int $maxHeartRate,
        ?int $averageCadence,
        int $movingTimeInSeconds,
        int $elapsedTimeInSeconds,
        ?string $deviceName,
        ?ConnectedSensors $connectedSensors,
        int $totalImageCount,
        array $localImagePaths,
        ?string $polyline,
        RouteGeography $routeGeography,
        ?string $weather,
        ?GearId $gearId,
        bool $isCommute,
        ?WorkoutType $workoutType,
    ): self {
        return new self(
            activityId: $activityId,
            startDateTime: $startDateTime,
            sportType: $sportType,
            worldType: $worldType,
            importSource: $importSource,
            externalReferenceId: $externalReferenceId,
            name: $name,
            description: $description,
            distance: $distance,
            elevation: $elevation,
            startingCoordinate: $startingCoordinate,
            calories: $calories,
            kilojoules: $kilojoules,
            averagePower: $averagePower,
            maxPower: $maxPower,
            averageSpeed: $averageSpeed,
            maxSpeed: $maxSpeed,
            averageHeartRate: $averageHeartRate,
            maxHeartRate: $maxHeartRate,
            averageCadence: $averageCadence,
            movingTimeInSeconds: $movingTimeInSeconds,
            elapsedTimeInSeconds: $elapsedTimeInSeconds,
            deviceName: $deviceName,
            connectedSensors: $connectedSensors,
            totalImageCount: $totalImageCount,
            localImagePaths: $localImagePaths,
            polyline: $polyline,
            routeGeography: $routeGeography,
            weather: $weather,
            gearId: $gearId,
            isCommute: $isCommute,
            workoutType: $workoutType
        );
    }

    public function getId(): ActivityId
    {
        return $this->activityId;
    }

    public function getStartDate(): SerializableDateTime
    {
        return $this->startDateTime;
    }

    public function withStartDateTime(SerializableDateTime $startDateTime): self
    {
        return $this->recordUpdate(clone ($this, [
            'startDateTime' => $startDateTime,
        ]));
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function getWorldType(): WorldType
    {
        return $this->worldType;
    }

    public function withWorldType(WorldType $worldType): self
    {
        return $this->recordUpdate(clone ($this, [
            'worldType' => $worldType,
        ]));
    }

    public function getImportSource(): ImportSource
    {
        return $this->importSource;
    }

    public function getExternalReferenceId(): ?ExternalReferenceId
    {
        return $this->externalReferenceId;
    }

    public function withSportType(SportType $sportType): self
    {
        return $this->recordUpdate(clone ($this, [
            'sportType' => $sportType,
        ]));
    }

    public function getStartingCoordinate(): ?Coordinate
    {
        return $this->startingCoordinate;
    }

    public function withStartingCoordinate(?Coordinate $coordinate): self
    {
        return $this->recordUpdate(clone ($this, [
            'startingCoordinate' => $coordinate,
        ]));
    }

    public function getGearId(): ?GearId
    {
        return $this->gearId;
    }

    public function getGearIdIncludingNone(): GearId
    {
        return $this->getGearId() ?? GearId::none();
    }

    public function withGear(
        ?GearId $gearId = null,
    ): self {
        return $this->recordUpdate(clone ($this, [
            'gearId' => $gearId,
        ]));
    }

    public function getWeather(): ?Weather
    {
        if (!$this->weather) {
            return null;
        }
        if ($decodedWeather = Json::decode($this->weather)) {
            return Weather::fromState($decodedWeather);
        }

        return null;
    }

    public function withWeather(?Weather $weather): self
    {
        return clone ($this, [
            'weather' => Json::encode($weather),
        ]);
    }

    /**
     * @return array<string>
     */
    public function getLocalImagePaths(): array
    {
        return array_map(
            fn (string $path): string => str_starts_with($path, '/') ? $path : '/'.$path,
            $this->localImagePaths
        );
    }

    /**
     * @param array<string> $localImagePaths
     */
    public function withLocalImagePaths(array $localImagePaths): self
    {
        $clone = clone ($this, [
            'localImagePaths' => $localImagePaths,
            'totalImageCount' => count($localImagePaths),
        ]);

        if ($this->getLocalImagePaths() !== $clone->getLocalImagePaths()) {
            $clone->recordThat(new ActivityImagesHaveBeenUpdated($clone->getId(), $clone->getStartDate()));
        }

        return $clone;
    }

    public function getTotalImageCount(): int
    {
        return $this->totalImageCount;
    }

    public function getOriginalName(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        $name = str_replace('Zwift - ', '', $this->getOriginalName());
        // Strip legacy gear-maintenance hashtags (e.g. "#sfs-chain-lubed") from the display name.
        $name = (string) preg_replace('/\s*#sfs-\S+/', '', $name);

        return trim($name);
    }

    public function getSanitizedName(): string
    {
        return Escape::forJsonEncode($this->getName());
    }

    public function withName(ActivityName $name): self
    {
        return $this->recordUpdate(clone ($this, [
            'name' => $name,
        ]));
    }

    public function getDescription(): string
    {
        return trim($this->description ?? '');
    }

    public function withDescription(?string $description): self
    {
        return $this->recordUpdate(clone ($this, [
            'description' => $description,
        ]));
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    public function withDistance(Kilometer $distance): self
    {
        return $this->recordUpdate(clone ($this, [
            'distance' => $distance,
        ]));
    }

    public function getDistanceInDisplayUnit(): NauticalMile|Kilometer
    {
        return $this->getSportType()->toDisplayDistance($this->getDistance());
    }

    public function getElevation(): Meter
    {
        return $this->elevation;
    }

    public function withElevation(Meter $elevation): self
    {
        return $this->recordUpdate(clone ($this, [
            'elevation' => $elevation,
        ]));
    }

    public function getCalories(): ?int
    {
        return $this->calories;
    }

    public function withCalories(?int $calories): self
    {
        return $this->recordUpdate(clone ($this, [
            'calories' => $calories,
        ]));
    }

    public function getKilojoules(): ?int
    {
        return $this->kilojoules;
    }

    public function getAveragePower(): ?int
    {
        return $this->averagePower;
    }

    public function getMaxPower(): ?int
    {
        return $this->maxPower;
    }

    public function getAverageSpeed(): KmPerHour
    {
        return $this->averageSpeed;
    }

    public function withAverageSpeed(KmPerHour $averageSpeed): self
    {
        return $this->recordUpdate(clone ($this, [
            'averageSpeed' => $averageSpeed,
        ]));
    }

    public function getAverageSpeedInDisplayUnit(): Knot|KmPerHour
    {
        return $this->getSportType()->toDisplaySpeed($this->getAverageSpeed());
    }

    public function getPaceInSecPerKm(): SecPerKm
    {
        return $this->getAverageSpeed()->toMetersPerSecond()->toSecPerKm();
    }

    public function getPaceInSecPer100Meter(): SecPer100Meter
    {
        return $this->getAverageSpeed()->toMetersPerSecond()->toSecPerKm()->toSecPer100Meter();
    }

    public function getMaxSpeed(): KmPerHour
    {
        return $this->maxSpeed;
    }

    public function withMaxSpeed(KmPerHour $maxSpeed): self
    {
        return $this->recordUpdate(clone ($this, [
            'maxSpeed' => $maxSpeed,
        ]));
    }

    public function getMaxSpeedInDisplayUnit(): Knot|KmPerHour
    {
        return $this->getSportType()->toDisplaySpeed($this->getMaxSpeed());
    }

    public function getAverageHeartRate(): ?int
    {
        return $this->averageHeartRate;
    }

    public function getMaxHeartRate(): ?int
    {
        return $this->maxHeartRate;
    }

    public function getAverageCadence(): ?int
    {
        return $this->averageCadence;
    }

    public function getMovingTimeInSeconds(): int
    {
        return $this->movingTimeInSeconds;
    }

    public function getMovingTimeInHours(): float
    {
        return round($this->movingTimeInSeconds / 3600, 1);
    }

    public function withMovingTimeInSeconds(int $movingTimeInSeconds): self
    {
        return $this->recordUpdate(clone ($this, [
            'movingTimeInSeconds' => $movingTimeInSeconds,
        ]));
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsClock($this->getMovingTimeInSeconds());
    }

    public function getElapsedTimeInSeconds(): int
    {
        return $this->elapsedTimeInSeconds;
    }

    public function withElapsedTimeInSeconds(int $elapsedTimeInSeconds): self
    {
        return $this->recordUpdate(clone ($this, [
            'elapsedTimeInSeconds' => $elapsedTimeInSeconds,
        ]));
    }

    public function getElapsedTimeFormatted(): string
    {
        return $this->formatDurationAsClock($this->getElapsedTimeInSeconds());
    }

    public function getUrl(): string
    {
        return 'https://www.strava.com/activities/'.$this->getId()->toUnprefixedString();
    }

    public function getEncodedPolyline(): ?EncodedPolyline
    {
        return EncodedPolyline::fromOptionalString($this->polyline);
    }

    public function withPolyline(?string $polyline): self
    {
        return $this->recordUpdate(clone ($this, [
            'polyline' => $polyline,
        ]));
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function withDeviceName(?string $deviceName): self
    {
        return $this->recordUpdate(clone ($this, [
            'deviceName' => $deviceName,
        ]));
    }

    public function getConnectedSensors(): ?ConnectedSensors
    {
        return $this->connectedSensors;
    }

    public function getDeviceId(): RecordingDeviceId
    {
        return RecordingDeviceId::fromName($this->getDeviceName() ?? 'none');
    }

    public function isCommute(): bool
    {
        return $this->isCommute;
    }

    public function withCommute(bool $isCommute): self
    {
        return $this->recordUpdate(clone ($this, [
            'isCommute' => $isCommute,
        ]));
    }

    public function getWorkoutType(): ?WorkoutType
    {
        return $this->workoutType;
    }

    public function withWorkoutType(?WorkoutType $workoutType): self
    {
        return $this->recordUpdate(clone ($this, [
            'workoutType' => $workoutType,
        ]));
    }

    public function isZwiftRide(): bool
    {
        return WorldType::ZWIFT === $this->getWorldType();
    }

    public function isRouvyRide(): bool
    {
        return WorldType::ROUVY === $this->getWorldType();
    }

    public function isMyWhooshRide(): bool
    {
        return WorldType::MY_WHOOSH === $this->getWorldType();
    }

    public function getLeafletMap(): ?LeafletMap
    {
        if (!$this->getEncodedPolyline() instanceof EncodedPolyline) {
            return null;
        }
        if (!$this->isZwiftRide()) {
            return new RealWorldMap();
        }
        if (!($startingCoordinate = $this->getStartingCoordinate()) instanceof Coordinate) {
            return null;
        }

        try {
            return ZwiftMap::forStartingCoordinate($startingCoordinate);
        } catch (CouldNotDetermineZwiftMap) {
            // Very old Zwift activities have routes that we don't have corresponding maps for.
        }

        return null;
    }

    public function getRouteGeography(): RouteGeography
    {
        return $this->routeGeography;
    }

    public function withRouteGeography(RouteGeography $routeGeography): self
    {
        return $this->recordUpdate(clone ($this, [
            'routeGeography' => $routeGeography,
        ]));
    }

    public function hasMappableRoute(): bool
    {
        return $this->sportType->supportsReverseGeocoding()
            && WorldType::REAL_WORLD === $this->worldType
            && !in_array($this->polyline, [null, '', '0'], true)
            && !is_null($this->routeGeography->getStartingPointCountryCode());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeSignature(): ?array
    {
        if (!$this->hasMappableRoute()) {
            return null;
        }

        return [
            'name' => $this->getName(),
            'distance' => $this->distance->toFloat(),
            'polyline' => $this->polyline,
            'routeGeography' => $this->routeGeography->jsonSerialize(),
            'sportType' => $this->sportType->value,
            'startDateTime' => $this->startDateTime->getTimestamp(),
            'isCommute' => $this->isCommute,
            'workoutType' => $this->workoutType?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateSignature(): array
    {
        return [
            'startDateTime' => $this->startDateTime->getTimestamp(),
            'name' => $this->getOriginalName(),
            'description' => $this->description,
            'deviceName' => $this->deviceName,
            'sportType' => $this->sportType->value,
            'worldType' => $this->worldType->value,
            'distance' => $this->distance->toFloat(),
            'averageSpeed' => $this->averageSpeed->toFloat(),
            'maxSpeed' => $this->maxSpeed->toFloat(),
            'movingTimeInSeconds' => $this->movingTimeInSeconds,
            'elapsedTimeInSeconds' => $this->elapsedTimeInSeconds,
            'elevation' => $this->elevation->toFloat(),
            'calories' => $this->calories,
            'polyline' => $this->polyline,
            'startingCoordinateLatitude' => $this->startingCoordinate?->getLatitude()->toFloat(),
            'startingCoordinateLongitude' => $this->startingCoordinate?->getLongitude()->toFloat(),
            'routeGeography' => $this->routeGeography->jsonSerialize(),
            'gearId' => (string) $this->gearId,
            'isCommute' => $this->isCommute,
            'workoutType' => $this->workoutType?->value,
        ];
    }

    private function recordUpdate(self $clone): self
    {
        if ($this->routeSignature() !== $clone->routeSignature()) {
            $clone->recordThat(new ActivityRouteWasUpdated());
        }
        if ($this->updateSignature() !== $clone->updateSignature()) {
            $clone->recordOnlyOnce(new ActivityWasUpdated(
                activityId: $clone->getId(),
                startDate: $clone->getStartDate(),
                previousStartDate: $this->getStartDate()
            ));
        }

        return $clone;
    }

    /**
     * @return string[]
     */
    public function getSearchables(): array
    {
        return array_map(strtolower(...), [$this->getName()]);
    }

    /**
     * @return array<string, string|int|string[]>
     */
    public function getFilterables(UnitSystem $unitSystem): array
    {
        return array_filter([
            'sportType' => $this->getSportType()->value,
            'start-date' => $this->getStartDate()->getTimestamp() * 1000, // JS timestamp is in milliseconds,
            'countryCode' => $this->getRouteGeography()->getPassedThroughCountries(),
            'isCommute' => $this->isCommute() ? 'true' : 'false',
            'gear' => $this->getGearIdIncludingNone(),
            'workoutType' => $this->getWorkoutType()?->value,
            'device' => $this->getDeviceId(),
            'distance' => (int) round($this->getDistance()->toUnitSystem($unitSystem)->toFloat() * 10), // We don't want to filter on float values, but integers instead.
            'elevation' => (int) round($this->getElevation()->toUnitSystem($unitSystem)->toFloat() * 10),
        ]);
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getSortables(): array
    {
        return array_filter([
            'start-date' => $this->getStartDate()->getTimestamp(),
            'distance' => (int) ($this->getDistance()->toFloat() * 1000),
            'elevation' => (int) ($this->getElevation()->toFloat() * 1000),
            'moving-time' => $this->getMovingTimeInSeconds(),
            'power' => $this->getAveragePower(),
            'speed' => (int) ($this->getAverageSpeed()->toFloat() * 1000),
            'heart-rate' => $this->getAverageHeartRate(),
            'calories' => $this->getCalories(),
        ]);
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getSummables(UnitSystem $unitSystem): array
    {
        return [
            'distance' => round($this->getDistance()->toUnitSystem($unitSystem)->toFloat(), 2),
            'elevation' => $this->getElevation()->toUnitSystem($unitSystem)->toFloat(),
            'moving-time' => $this->getMovingTimeInSeconds() / 3600,
            'calories' => $this->getCalories() ?? 0,
        ];
    }
}

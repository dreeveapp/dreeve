<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\WorkoutType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Escape;
use App\Infrastructure\Time\Format\DateAndTimeFormat;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Route implements \JsonSerializable
{
    use ProvideMeasurementFormats;

    /**
     * @param array<int, array<float, float>> $coordinates
     */
    private function __construct(
        private ActivityId $activityId,
        private string $name,
        private Kilometer $distance,
        private array $coordinates,
        private RouteGeography $routeGeography,
        private SportType $sportType,
        private bool $isCommute,
        private ?WorkoutType $workoutType,
        private SerializableDateTime $on,
        private ?UnitSystem $unitSystem,
        private ?DateAndTimeFormat $dateAndTimeFormat,
        private ?string $relativeActivityUri,
    ) {
    }

    /**
     * @param array<int, array<float, float>> $coordinates
     */
    public static function create(
        ActivityId $activityId,
        string $name,
        Kilometer $distance,
        array $coordinates,
        RouteGeography $routeGeography,
        SportType $sportType,
        bool $isCommute,
        ?WorkoutType $workoutType,
        SerializableDateTime $on,
    ): self {
        return new self(
            activityId: $activityId,
            name: $name,
            distance: $distance,
            coordinates: $coordinates,
            routeGeography: $routeGeography,
            sportType: $sportType,
            isCommute: $isCommute,
            workoutType: $workoutType,
            on: $on,
            unitSystem: null,
            dateAndTimeFormat: null,
            relativeActivityUri: null,
        );
    }

    public function getActivityId(): ActivityId
    {
        return $this->activityId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDistance(): Kilometer
    {
        return $this->distance;
    }

    /**
     * @return array<int, array<float, float>>
     */
    public function getCoordinates(): array
    {
        return $this->coordinates;
    }

    public function getRouteGeography(): RouteGeography
    {
        return $this->routeGeography;
    }

    public function getSportType(): SportType
    {
        return $this->sportType;
    }

    public function isCommute(): bool
    {
        return $this->isCommute;
    }

    public function getWorkoutType(): ?WorkoutType
    {
        return $this->workoutType;
    }

    public function getOn(): SerializableDateTime
    {
        return $this->on;
    }

    public function withUnitSystemAndDateTimeFormat(
        UnitSystem $unitSystem,
        DateAndTimeFormat $dateAndTimeFormat,
    ): self {
        return clone ($this, [
            'unitSystem' => $unitSystem,
            'dateAndTimeFormat' => $dateAndTimeFormat,
        ]);
    }

    public function withRelativeActivityUri(string $uri): self
    {
        return clone ($this, [
            'relativeActivityUri' => $uri,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $state = $this->getRouteGeography()->getStartingPointState();

        $distance = $this->getDistance();
        if (!is_null($this->unitSystem)) {
            $distance = $distance->toUnitSystem($this->unitSystem);
        }
        $distance = $this->formatNumber($distance->toFloat(), 1).$distance->getSymbol();

        $startDate = is_null($this->dateAndTimeFormat) ?
            $this->getOn()->format('d-m-Y') :
            $this->getOn()->format((string) $this->dateAndTimeFormat->getDateFormatNormal());

        return [
            'active' => true,
            'id' => $this->getActivityId(),
            'activityUrl' => $this->relativeActivityUri ?? null,
            'startDate' => $startDate,
            'distance' => $distance,
            'name' => Escape::forJsonEncode($this->getName()),
            'startLocation' => [
                'countryCode' => $this->getRouteGeography()->getStartingPointCountryCode(),
                'state' => $state ? Escape::forJsonEncode($state) : null,
            ],
            'filterables' => [
                'sportType' => $this->getSportType(),
                'start-date' => $this->getOn()->getTimestamp() * 1000, // JS timestamp is in milliseconds,
                'isCommute' => $this->isCommute() ? 'true' : 'false',
                'workoutType' => $this->getWorkoutType()?->value,
            ],
            'coordinates' => $this->getCoordinates(),
        ];
    }
}

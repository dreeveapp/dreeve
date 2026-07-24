<?php

namespace App\Domain\Gear;

use App\Domain\Activity\ActivityTypes;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity]
#[ORM\Table(name: 'Gear')]
#[ORM\Index(name: 'Gear_type', columns: ['type'])]
final readonly class Gear implements SupportsAITooling
{
    use ProvideTimeFormats;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private GearId $gearId,
        #[ORM\Column(type: 'datetime_immutable')]
        private SerializableDateTime $createdOn,
        private Meter $distanceInMeter,
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Column(type: 'boolean')]
        private bool $isRetired,
        #[ORM\Column(type: 'string', enumType: GearType::class, options: ['default' => GearType::IMPORTED->value])]
        private GearType $type,
        #[ORM\Column(type: 'string', nullable: true)]
        private ?string $localImagePath,
        private Seconds $movingTime,
        private Meter $elevation,
        private int $numberOfActivities,
        private int $totalCalories,
        private ActivityTypes $activityTypes,
        #[ORM\Embedded(class: Money::class)]
        private ?Money $purchasePrice,
    ) {
    }

    public static function create(
        GearId $gearId,
        SerializableDateTime $createdOn,
        string $name,
        bool $isRetired,
        GearType $type,
        ?string $localImagePath = null,
    ): self {
        return new self(
            gearId: $gearId,
            createdOn: $createdOn,
            distanceInMeter: Meter::zero(),
            name: $name,
            isRetired: $isRetired,
            type: $type,
            localImagePath: $localImagePath,
            movingTime: Seconds::zero(),
            elevation: Meter::zero(),
            numberOfActivities: 0,
            totalCalories: 0,
            activityTypes: ActivityTypes::empty(),
            purchasePrice: null,
        );
    }

    public static function fromState(
        GearId $gearId,
        Meter $distanceInMeter,
        SerializableDateTime $createdOn,
        string $name,
        bool $isRetired,
        GearType $type,
        ?string $localImagePath,
        Seconds $movingTime,
        Meter $elevation,
        int $numberOfActivities,
        int $totalCalories,
        ?Money $purchasePrice,
    ): self {
        return new self(
            gearId: $gearId,
            createdOn: $createdOn,
            distanceInMeter: $distanceInMeter,
            name: $name,
            isRetired: $isRetired,
            type: $type,
            localImagePath: $localImagePath,
            movingTime: $movingTime,
            elevation: $elevation,
            numberOfActivities: $numberOfActivities,
            totalCalories: $totalCalories,
            activityTypes: ActivityTypes::empty(),
            purchasePrice: $purchasePrice,
        );
    }

    public function getId(): GearId
    {
        return $this->gearId;
    }

    public function getType(): GearType
    {
        return $this->type;
    }

    public function withName(string $name): self
    {
        return clone ($this, [
            'name' => $name,
        ]);
    }

    public function getOriginalName(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return sprintf('%s%s', $this->name, $this->isRetired() ? ' ☠️' : '');
    }

    public function getDistance(): Kilometer
    {
        return $this->distanceInMeter->toKilometer();
    }

    public function getMovingTime(): Seconds
    {
        return $this->movingTime;
    }

    public function getElevation(): Meter
    {
        return $this->elevation;
    }

    public function getNumberOfActivities(): int
    {
        return $this->numberOfActivities;
    }

    public function getTotalCalories(): int
    {
        return $this->totalCalories;
    }

    public function getMovingTimeFormatted(): string
    {
        return $this->formatDurationAsHumanString($this->movingTime->toInt());
    }

    public function getMovingTimeInHours(): Hour
    {
        return $this->movingTime->toHour();
    }

    public function getAverageDistance(): Kilometer
    {
        if (0 === $this->numberOfActivities) {
            return Kilometer::zero();
        }

        return Kilometer::from($this->getDistance()->toFloat() / $this->numberOfActivities);
    }

    public function getAverageSpeed(): KmPerHour
    {
        $movingTimeInSeconds = $this->movingTime->toFloat();
        if (0.0 === $movingTimeInSeconds) {
            return KmPerHour::zero();
        }

        return KmPerHour::from(($this->getDistance()->toFloat() / $movingTimeInSeconds) * 3600);
    }

    public function getRelativeCostPerHour(): ?Money
    {
        $movingTimeInHours = $this->getMovingTimeInHours()->toInt();

        return $this->getPurchasePrice()?->divide($movingTimeInHours > 0 ? $movingTimeInHours : 1);
    }

    public function getRelativeCostPerWorkout(): ?Money
    {
        return $this->getPurchasePrice()?->divide($this->numberOfActivities > 0 ? $this->numberOfActivities : 1);
    }

    public function getRelativeCostPerDistanceUnit(UnitSystem $unitSystem): ?Money
    {
        $distance = $this->getDistance()->toUnitSystem($unitSystem)->toInt();

        return $this->getPurchasePrice()?->divide($distance > 0 ? $distance : 1);
    }

    public function isRetired(): bool
    {
        return $this->isRetired;
    }

    public function withIsRetired(bool $isRetired): self
    {
        return clone ($this, [
            'isRetired' => $isRetired,
        ]);
    }

    public function getStatus(): GearStatus
    {
        return $this->isRetired() ? GearStatus::RETIRED : GearStatus::ACTIVE;
    }

    public function getCreatedOn(): SerializableDateTime
    {
        return $this->createdOn;
    }

    public function getLocalImagePath(): ?string
    {
        if (!isset($this->localImagePath)) {
            return null;
        }

        return str_starts_with($this->localImagePath, '/') ? $this->localImagePath : '/'.$this->localImagePath;
    }

    public function withLocalImagePath(?string $localImagePath): self
    {
        return clone ($this, [
            'localImagePath' => $localImagePath,
        ]);
    }

    public function getActivityTypes(): ActivityTypes
    {
        return $this->activityTypes;
    }

    public function withActivityTypes(ActivityTypes $activityTypes): self
    {
        return clone ($this, [
            'activityTypes' => $activityTypes,
        ]);
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function withPurchasePrice(Money $purchasePrice): self
    {
        return clone ($this, [
            'purchasePrice' => $purchasePrice,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportForAITooling(): array
    {
        return [
            'id' => $this->getId()->toUnprefixedString(),
            'totalDistanceInKilometer' => $this->getDistance(),
            'name' => $this->getName(),
            'isRetired' => $this->isRetired(),
        ];
    }
}

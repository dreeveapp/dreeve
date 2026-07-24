<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\Gear;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearType;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Money\Money;

final class GearBuilder
{
    private GearId $gearId;
    private SerializableDateTime $createdOn;
    private Meter $distanceInMeter;
    private string $name = 'Existing gear';
    private bool $isRetired = false;
    private GearType $type = GearType::IMPORTED;
    private Seconds $movingTime;
    private Meter $elevation;
    private int $numberOfActivities = 0;
    private int $totalCalories = 0;
    private ?string $localImagePath = null;
    private ?Money $purchasePrice = null;

    private function __construct()
    {
        $this->gearId = GearId::fromUnprefixed('1');
        $this->createdOn = SerializableDateTime::fromString('2023-10-10');
        $this->distanceInMeter = Meter::from(10023);
        $this->movingTime = Seconds::zero();
        $this->elevation = Meter::zero();
    }

    public static function fromDefaults(): self
    {
        return new self();
    }

    public function build(): Gear
    {
        return Gear::fromState(
            gearId: $this->gearId,
            distanceInMeter: $this->distanceInMeter,
            createdOn: $this->createdOn,
            name: $this->name,
            isRetired: $this->isRetired,
            type: $this->type,
            localImagePath: $this->localImagePath,
            movingTime: $this->movingTime,
            elevation: $this->elevation,
            numberOfActivities: $this->numberOfActivities,
            totalCalories: $this->totalCalories,
            purchasePrice: $this->purchasePrice,
        );
    }

    public function withGearType(GearType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function withLocalImagePath(?string $localImagePath): self
    {
        $this->localImagePath = $localImagePath;

        return $this;
    }

    public function withPurchasePrice(?Money $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function withMovingTime(Seconds $movingTime): self
    {
        $this->movingTime = $movingTime;

        return $this;
    }

    public function withElevation(Meter $elevation): self
    {
        $this->elevation = $elevation;

        return $this;
    }

    public function withNumberOfActivities(int $numberOfActivities): self
    {
        $this->numberOfActivities = $numberOfActivities;

        return $this;
    }

    public function withTotalCalories(int $totalCalories): self
    {
        $this->totalCalories = $totalCalories;

        return $this;
    }

    public function withGearId(GearId $gearId): self
    {
        $this->gearId = $gearId;

        return $this;
    }

    public function withDistanceInMeter(Meter $distanceInMeter): self
    {
        $this->distanceInMeter = $distanceInMeter;

        return $this;
    }

    public function withCreatedOn(SerializableDateTime $createdOn): self
    {
        $this->createdOn = $createdOn;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function withIsRetired(bool $isRetired): self
    {
        $this->isRetired = $isRetired;

        return $this;
    }
}

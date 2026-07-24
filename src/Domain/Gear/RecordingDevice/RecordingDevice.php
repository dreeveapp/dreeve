<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice;

use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity]
#[ORM\Table(name: 'RecordingDevice')]
final readonly class RecordingDevice
{
    use ProvideTimeFormats;

    private function __construct(
        #[ORM\Id, ORM\Column(type: 'string', unique: true)]
        private RecordingDeviceId $id,
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Embedded(class: Money::class)]
        private ?Money $purchasePrice,
        private Seconds $timeTracked,
        private Kilometer $distanceTracked,
        private Meter $elevationTracked,
        private int $numberOfWorkouts,
    ) {
    }

    public static function create(
        string $name,
        ?Money $purchasePrice,
    ): self {
        return new self(
            id: RecordingDeviceId::fromName($name),
            name: $name,
            purchasePrice: $purchasePrice,
            timeTracked: Seconds::zero(),
            distanceTracked: Kilometer::zero(),
            elevationTracked: Meter::zero(),
            numberOfWorkouts: 0,
        );
    }

    public static function fromState(
        RecordingDeviceId $id,
        string $name,
        Seconds $timeTracked,
        Kilometer $distanceTracked,
        Meter $elevationTracked,
        int $activityCount,
        ?Money $purchasePrice,
    ): self {
        return new self(
            id: $id,
            name: $name,
            purchasePrice: $purchasePrice,
            timeTracked: $timeTracked,
            distanceTracked: $distanceTracked,
            elevationTracked: $elevationTracked,
            numberOfWorkouts: $activityCount,
        );
    }

    public function getId(): RecordingDeviceId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeTracked(): Seconds
    {
        return $this->timeTracked;
    }

    public function getTimeTrackedFormatted(): string
    {
        return $this->formatDurationAsHumanString($this->getTimeTracked()->toInt());
    }

    public function getDistanceTracked(): Kilometer
    {
        return $this->distanceTracked;
    }

    public function getElevationTracked(): Meter
    {
        return $this->elevationTracked;
    }

    public function getNumberOfWorkouts(): int
    {
        return $this->numberOfWorkouts;
    }

    public function getPurchasePrice(): ?Money
    {
        return $this->purchasePrice;
    }

    public function getRelativeCostPerHour(): ?Money
    {
        $timeTrackedInHours = $this->getTimeTracked()->toHour()->toInt();

        return $this->getPurchasePrice()?->divide($timeTrackedInHours > 0 ? $timeTrackedInHours : 1);
    }

    public function getRelativeCostPerWorkout(): ?Money
    {
        return $this->getPurchasePrice()?->divide($this->getNumberOfWorkouts() > 0 ? $this->getNumberOfWorkouts() : 1);
    }

    public function getRelativeCostPerDistanceUnit(UnitSystem $unitSystem): ?Money
    {
        $distance = $this->getDistanceTracked()->toUnitSystem($unitSystem)->toInt();

        return $this->getPurchasePrice()?->divide($distance > 0 ? $distance : 1);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Gear;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Calendar\Month;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class MonthlyUsage
{
    private function __construct(
        private SerializableDateTime $firstUsedOn,
        private SerializableDateTime $lastUsedOn,
        private SportTypes $sportTypes,
        /** @var array<string, Kilometer> */
        private array $distancePerMonth,
    ) {
    }

    /**
     * @param non-empty-list<array<string, mixed>> $results
     */
    public static function fromQueryResults(array $results): self
    {
        $sportTypes = [];
        $distancePerMonth = [];
        $firstUsedOn = [];
        $lastUsedOn = [];
        foreach ($results as $result) {
            $distancePerMonth[(string) $result['month']] = Meter::from((float) $result['distance'])->toKilometer();
            $firstUsedOn[] = (string) $result['firstUsedOn'];
            $lastUsedOn[] = (string) $result['lastUsedOn'];
            foreach (explode(',', (string) $result['sportTypes']) as $sportType) {
                $sportTypes[$sportType] = SportType::from($sportType);
            }
        }

        return new self(
            firstUsedOn: SerializableDateTime::fromString(min($firstUsedOn)),
            lastUsedOn: SerializableDateTime::fromString(max($lastUsedOn)),
            sportTypes: SportTypes::fromArray(array_values($sportTypes)),
            distancePerMonth: $distancePerMonth,
        );
    }

    public function getFirstUsedOn(): SerializableDateTime
    {
        return $this->firstUsedOn;
    }

    public function getLastUsedOn(): SerializableDateTime
    {
        return $this->lastUsedOn;
    }

    public function getSportTypes(): SportTypes
    {
        return $this->sportTypes;
    }

    public function getDistanceFor(Month $month): Kilometer
    {
        return $this->distancePerMonth[$month->getId()] ?? Kilometer::zero();
    }
}

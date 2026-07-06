<?php

declare(strict_types=1);

namespace App\Domain\Athlete;

use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormula;
use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormula;
use App\Domain\Integration\AI\SupportsAITooling;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Athlete implements SupportsAITooling
{
    private function __construct(
        private string $athleteId,
        private SerializableDateTime $birthDate,
        private ?string $firstName,
        private ?string $lastName,
        private ?string $gender,
        private MaxHeartRateFormula $maxHeartRateFormula,
        private RestingHeartRateFormula $restingHeartRateFormula,
    ) {
    }

    public static function create(
        string $athleteId,
        SerializableDateTime $birthDate,
        ?string $firstName,
        ?string $lastName,
        ?string $gender,
        MaxHeartRateFormula $maxHeartRateFormula,
        RestingHeartRateFormula $restingHeartRateFormula,
    ): self {
        return new self(
            athleteId: $athleteId,
            birthDate: $birthDate,
            firstName: $firstName,
            lastName: $lastName,
            gender: $gender,
            maxHeartRateFormula: $maxHeartRateFormula,
            restingHeartRateFormula: $restingHeartRateFormula,
        );
    }

    public function getAthleteId(): string
    {
        return $this->athleteId;
    }

    public function getBirthDate(): SerializableDateTime
    {
        return $this->birthDate;
    }

    public function getAgeInYears(SerializableDateTime $on): int
    {
        return $this->getBirthDate()->diff($on)->y;
    }

    public function getRestingHeartRate(SerializableDateTime $on): int
    {
        return $this->restingHeartRateFormula->calculate(
            age: $this->getAgeInYears($on),
            on: $on
        );
    }

    public function getMaxHeartRate(SerializableDateTime $on): int
    {
        return $this->maxHeartRateFormula->calculate(
            age: $this->getAgeInYears($on),
            on: $on
        );
    }

    public function getName(): Name
    {
        return Name::fromString(sprintf('%s %s', $this->firstName ?? 'John', $this->lastName ?? 'Doe'));
    }

    public function getFirstLetterOfFirstName(): string
    {
        return substr($this->firstName ?? 'J', 0, 1);
    }

    public function isMale(): bool
    {
        return 'M' === strtoupper($this->gender ?? 'M');
    }

    /**
     * @return array<string, mixed>
     */
    public function exportForAITooling(): array
    {
        return [
            'id' => $this->athleteId,
            'firstname' => $this->firstName,
            'lastname' => $this->lastName,
            'sex' => $this->gender,
            'birthDate' => $this->birthDate->format('Y-m-d'),
        ];
    }
}

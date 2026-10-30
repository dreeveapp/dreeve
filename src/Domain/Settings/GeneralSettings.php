<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Application\ProfilePictureUrl;
use App\Domain\Athlete\Athlete;
use App\Domain\Athlete\AthleteBirthDate;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneConfiguration;
use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormulas;
use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormulas;

final readonly class GeneralSettings
{
    private function __construct(
        private ?ProfilePictureUrl $profilePictureUrl,
        private Athlete $athlete,
        private HeartRateZoneConfiguration $heartRateZoneConfiguration,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        $birthday = $data['birthday'] ?? null;
        if (!is_string($birthday) || '' === trim($birthday)) {
            throw AthleteHasNotBeenConfigured::because('A "birthday" is required for the athlete in the general settings');
        }

        $firstName = $data['firstName'] ?? null;
        if (!is_string($firstName) || '' === trim($firstName)) {
            throw AthleteHasNotBeenConfigured::because('A "firstName" is required for the athlete in the general settings');
        }

        $lastName = $data['lastName'] ?? null;
        if (!is_string($lastName) || '' === trim($lastName)) {
            throw AthleteHasNotBeenConfigured::because('A "lastName" is required for the athlete in the general settings');
        }

        $maxHeartRateFormula = $data['maxHeartRateFormula'] ?? null;
        if (!is_string($maxHeartRateFormula) && !is_array($maxHeartRateFormula)) {
            throw AthleteHasNotBeenConfigured::because('A "maxHeartRateFormula" is required for the athlete in the general settings');
        }

        $restingHeartRateFormula = $data['restingHeartRateFormula'] ?? 'heuristicAgeBased';
        if (!is_array($restingHeartRateFormula) && !is_int($restingHeartRateFormula)
            && (!is_string($restingHeartRateFormula) || '' === trim($restingHeartRateFormula))) {
            $restingHeartRateFormula = 'heuristicAgeBased';
        }

        $athleteBirthDate = AthleteBirthDate::fromString($birthday);
        $gender = $data['gender'] ?? null;

        return new self(
            profilePictureUrl: ProfilePictureUrl::fromOptionalString(is_string($data['profilePictureUrl'] ?? null) ? $data['profilePictureUrl'] : null),
            athlete: Athlete::create(
                athleteId: substr(hash('sha256', sprintf('%s|%s|%s', $firstName, $lastName, $athleteBirthDate->format('Y-m-d'))), 0, 12),
                birthDate: $athleteBirthDate,
                firstName: $firstName,
                lastName: $lastName,
                gender: $gender,
                maxHeartRateFormula: new MaxHeartRateFormulas()->determineFormula($maxHeartRateFormula),
                restingHeartRateFormula: new RestingHeartRateFormulas()->determineFormula($restingHeartRateFormula),
            ),
            heartRateZoneConfiguration: HeartRateZoneConfiguration::fromArray($data['heartRateZones'] ?? []),
        );
    }

    public function getProfilePictureUrl(): ?ProfilePictureUrl
    {
        return $this->profilePictureUrl;
    }

    public function getAthlete(): Athlete
    {
        return $this->athlete;
    }

    public function getHeartRateZoneConfiguration(): HeartRateZoneConfiguration
    {
        return $this->heartRateZoneConfiguration;
    }
}

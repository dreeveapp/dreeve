<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Application\AppSubTitle;
use App\Application\ProfilePictureUrl;
use App\Domain\Athlete\Athlete;
use App\Domain\Athlete\AthleteBirthDate;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneConfiguration;
use App\Domain\Athlete\MaxHeartRate\MaxHeartRateFormulas;
use App\Domain\Athlete\RestingHeartRate\RestingHeartRateFormulas;
use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Ftp\FtpHistory;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;

final readonly class GeneralSettings
{
    /**
     * @param array<string, mixed> $weightHistory
     */
    private function __construct(
        private ?AppSubTitle $appSubTitle,
        private ?ProfilePictureUrl $profilePictureUrl,
        private Athlete $athlete,
        private HeartRateZoneConfiguration $heartRateZoneConfiguration,
        private FtpHistory $ftpHistory,
        private array $weightHistory,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $athlete = is_array($data['athlete'] ?? null) ? $data['athlete'] : [];

        $birthday = $athlete['birthday'] ?? null;
        if (!is_string($birthday) || '' === trim($birthday)) {
            throw AthleteHasNotBeenConfigured::because('A "birthday" is required for the athlete in the general settings');
        }

        $maxHeartRateFormula = $athlete['maxHeartRateFormula'] ?? null;
        if (!is_string($maxHeartRateFormula) && !is_array($maxHeartRateFormula)) {
            throw AthleteHasNotBeenConfigured::because('A "maxHeartRateFormula" is required for the athlete in the general settings');
        }

        $restingHeartRateFormula = $athlete['restingHeartRateFormula'] ?? 'heuristicAgeBased';
        if (!is_array($restingHeartRateFormula) && !is_int($restingHeartRateFormula)
            && (!is_string($restingHeartRateFormula) || '' === trim($restingHeartRateFormula))) {
            $restingHeartRateFormula = 'heuristicAgeBased';
        }

        $athleteBirthDate = AthleteBirthDate::fromString($birthday);
        $firstName = is_string($athlete['firstName'] ?? null) ? $athlete['firstName'] : null;
        $lastName = is_string($athlete['lastName'] ?? null) ? $athlete['lastName'] : null;

        return new self(
            appSubTitle: AppSubTitle::fromOptionalString(is_string($data['appSubTitle'] ?? null) ? $data['appSubTitle'] : null),
            profilePictureUrl: ProfilePictureUrl::fromOptionalString(is_string($data['profilePictureUrl'] ?? null) ? $data['profilePictureUrl'] : null),
            athlete: Athlete::create(
                athleteId: substr(hash('sha256', sprintf('%s|%s|%s', $firstName ?? '', $lastName ?? '', $athleteBirthDate->format('Y-m-d'))), 0, 12),
                birthDate: $athleteBirthDate,
                firstName: $firstName,
                lastName: $lastName,
                gender: is_string($athlete['gender'] ?? null) ? $athlete['gender'] : null,
                maxHeartRateFormula: new MaxHeartRateFormulas()->determineFormula($maxHeartRateFormula),
                restingHeartRateFormula: new RestingHeartRateFormulas()->determineFormula($restingHeartRateFormula),
            ),
            heartRateZoneConfiguration: HeartRateZoneConfiguration::fromArray(is_array($athlete['heartRateZones'] ?? null) ? $athlete['heartRateZones'] : []),
            ftpHistory: FtpHistory::fromArray(is_array($athlete['ftpHistory'] ?? null) ? $athlete['ftpHistory'] : []),
            weightHistory: is_array($athlete['weightHistory'] ?? null) ? $athlete['weightHistory'] : [],
        );
    }

    public function getAppSubTitle(): ?AppSubTitle
    {
        return $this->appSubTitle;
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

    public function getFtpHistory(): FtpHistory
    {
        return $this->ftpHistory;
    }

    public function getAthleteWeightHistory(UnitSystem $unitSystem): AthleteWeightHistory
    {
        /** @var array<string, float> $weightHistory */
        $weightHistory = $this->weightHistory;

        return AthleteWeightHistory::fromArray($weightHistory, $unitSystem);
    }
}

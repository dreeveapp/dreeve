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
        $athlete = $data['athlete'] ?? [];

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
        $firstName = $athlete['firstName'] ?? null;
        $lastName = $athlete['lastName'] ?? null;

        return new self(
            appSubTitle: AppSubTitle::fromOptionalString($data['appSubTitle'] ?? null),
            profilePictureUrl: ProfilePictureUrl::fromOptionalString(is_string($data['profilePictureUrl'] ?? null) ? $data['profilePictureUrl'] : null),
            athlete: Athlete::create(
                athleteId: substr(hash('sha256', sprintf('%s|%s|%s', $firstName ?? '', $lastName ?? '', $athleteBirthDate->format('Y-m-d'))), 0, 12),
                birthDate: $athleteBirthDate,
                firstName: $firstName,
                lastName: $lastName,
                gender: $athlete['gender'] ?? null,
                maxHeartRateFormula: new MaxHeartRateFormulas()->determineFormula($maxHeartRateFormula),
                restingHeartRateFormula: new RestingHeartRateFormulas()->determineFormula($restingHeartRateFormula),
            ),
            heartRateZoneConfiguration: HeartRateZoneConfiguration::fromArray($athlete['heartRateZones'] ?? []),
            ftpHistory: FtpHistory::fromArray($athlete['ftpHistory'] ?? []),
            weightHistory: $athlete['weightHistory'] ?? [],
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

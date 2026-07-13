<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\ActivityType;
use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\GeneralSettings;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class GeneralSettingsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function validData(): array
    {
        return [
            'appSubTitle' => 'The King',
            'profilePictureUrl' => 'https://example.com/me.png',
            'athlete' => [
                'birthday' => '1989-08-14',
                'firstName' => 'Robin',
                'lastName' => 'Ingelbrecht',
                'gender' => 'M',
                'maxHeartRateFormula' => 'fox',
                'weightHistory' => [['on' => '2024-01-01', 'weight' => 70]],
                'ftpHistory' => [['on' => '2023-01-01', 'ftp' => 250]],
            ],
        ];
    }

    public function testItBuildsTheAthleteFromTheSettings(): void
    {
        $athlete = GeneralSettings::fromArray(self::validData())->getAthlete();

        $this->assertSame('Robin Ingelbrecht', (string) $athlete->getName());
        $this->assertSame('R', $athlete->getFirstLetterOfFirstName());
        $this->assertTrue($athlete->isMale());
        // Formulas are set on the athlete, so heart rate can be resolved.
        $this->assertGreaterThan(0, $athlete->getMaxHeartRate(SerializableDateTime::fromString('2024-01-01')));
        $this->assertGreaterThan(0, $athlete->getRestingHeartRate(SerializableDateTime::fromString('2024-01-01')));
    }

    public function testTheAthleteIdIsDeterministic(): void
    {
        $first = GeneralSettings::fromArray(self::validData())->getAthlete()->getAthleteId();
        $second = GeneralSettings::fromArray(self::validData())->getAthlete()->getAthleteId();

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
    }

    public function testItExposesTheOtherSettings(): void
    {
        $settings = GeneralSettings::fromArray(self::validData());

        $this->assertSame('The King', (string) $settings->getAppSubTitle());
        $this->assertSame('https://example.com/me.png', (string) $settings->getProfilePictureUrl());
        $this->assertNotEmpty($settings->getFtpHistory()->findAll(ActivityType::RIDE));
        $this->assertNotEmpty($settings->getAthleteWeightHistory(UnitSystem::METRIC)->findAll());
    }

    public function testItAppliesDefaultsForOptionalSettings(): void
    {
        $settings = GeneralSettings::fromArray([
            'athlete' => [
                'birthday' => '1989-08-14',
                'maxHeartRateFormula' => 'fox',
            ],
        ]);

        $this->assertNull($settings->getAppSubTitle());
        $this->assertNull($settings->getProfilePictureUrl());
        $this->assertEmpty($settings->getAthleteWeightHistory(UnitSystem::METRIC)->findAll());
        // Resting heart rate defaults to the heuristic age-based formula.
        $this->assertGreaterThan(0, $settings->getAthlete()->getRestingHeartRate(SerializableDateTime::fromString('2024-01-01')));
    }

    public function testItThrowsWhenBirthdayIsMissing(): void
    {
        $this->expectExceptionObject(new AthleteHasNotBeenConfigured('A "birthday" is required for the athlete in the general settings'));

        GeneralSettings::fromArray(['athlete' => ['maxHeartRateFormula' => 'fox']]);
    }

    public function testItThrowsWhenMaxHeartRateFormulaIsMissing(): void
    {
        $this->expectExceptionObject(new AthleteHasNotBeenConfigured('A "maxHeartRateFormula" is required for the athlete in the general settings'));

        GeneralSettings::fromArray(['athlete' => ['birthday' => '1989-08-14']]);
    }

    public function testItThrowsWhenNothingIsConfigured(): void
    {
        $this->expectExceptionObject(new AthleteHasNotBeenConfigured('A "birthday" is required for the athlete in the general settings'));

        GeneralSettings::fromArray(null);
    }
}

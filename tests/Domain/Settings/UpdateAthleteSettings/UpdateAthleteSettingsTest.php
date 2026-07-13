<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class UpdateAthleteSettingsTest extends TestCase
{
    public function testItThrowsWhenAthleteIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('"athlete" must be an object.'));

        UpdateAthleteSettings::fromPayload([]);
    }

    public function testItThrowsWhenAthleteIsNotAnArray(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('"athlete" must be an object.'));

        UpdateAthleteSettings::fromPayload(['athlete' => 'not-an-array']);
    }

    public function testItThrowsWhenBirthdayIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "birthday" is required for the athlete in the general settings'));

        UpdateAthleteSettings::fromPayload([
            'athlete' => ['firstName' => 'Jane'],
        ]);
    }

    public function testItThrowsWhenMaxHeartRateFormulaIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A "maxHeartRateFormula" is required for the athlete in the general settings'));

        UpdateAthleteSettings::fromPayload([
            'athlete' => ['birthday' => '1990-01-01'],
        ]);
    }

    public function testItDeserializes(): void
    {
        $athlete = [
            'birthday' => '1990-01-01',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'maxHeartRateFormula' => 'fox',
        ];

        $command = UpdateAthleteSettings::fromPayload(['athlete' => $athlete]);

        $this->assertSame($athlete, $command->getAthlete());
    }

    public function testItNormalizesTheHeartRateFormulas(): void
    {
        $command = UpdateAthleteSettings::fromPayload(['athlete' => [
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => 'dateRangeBased',
            'maxHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '180']],
            'restingHeartRateFormula' => 'fixed',
            'restingHeartRateFormulaFixedValue' => '58',
        ]]);

        $this->assertSame([
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => ['2023-01-01' => 180],
            'restingHeartRateFormula' => 58,
        ], $command->getAthlete());
    }

    public function testItThrowsWhenTheFixedRestingHeartRateIsMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('The resting heart rate formula needs a heart rate greater than zero'));

        UpdateAthleteSettings::fromPayload(['athlete' => [
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => 'fox',
            'restingHeartRateFormula' => 'fixed',
            'restingHeartRateFormulaFixedValue' => '',
        ]]);
    }
}
